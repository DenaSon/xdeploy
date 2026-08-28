<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Application\Billing\Events\PaymentStatusChanged;
use App\Application\Notifications\NotificationTopic;
use App\Application\User\Actions\FindOrCreateUserAction;
use App\Application\User\Events\UserRegistered;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use App\Listeners\SendAdminPaymentSucceededNotification;
use App\Listeners\SendAdminUserRegisteredNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Admin\AdminPaymentSucceededNotification;
use App\Notifications\Admin\AdminUserRegisteredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

final class AdminTelegramAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_event_is_emitted_only_when_user_is_created(): void
    {
        Event::fake([
            UserRegistered::class,
        ]);

        $phone = PhoneNumber::from('09123456789');
        $action = app(FindOrCreateUserAction::class);

        $created = $action->handle($phone);
        $existing = $action->handle($phone);

        $this->assertSame(
            $created->getKey(),
            $existing->getKey(),
        );

        Event::assertDispatchedTimes(
            UserRegistered::class,
            1,
        );
        Event::assertDispatched(
            UserRegistered::class,
            static fn (UserRegistered $event): bool => $event->userId === (int) $created->getKey(),
        );
    }

    public function test_registration_succeeds_when_admin_alert_dispatch_fails(): void
    {
        Event::forget(
            UserRegistered::class,
        );
        Event::listen(
            UserRegistered::class,
            static function (UserRegistered $event): void {
                throw new RuntimeException(
                    'notification queue unavailable',
                );
            },
        );

        $phone = PhoneNumber::from('09123456789');

        $user = app(FindOrCreateUserAction::class)->handle(
            $phone,
        );

        $this->assertDatabaseHas(
            'users',
            [
                'id' => $user->getKey(),
                'phone' => (string) $phone,
            ],
        );
    }

    public function test_registration_alert_targets_admins_only_masks_phone_and_is_idempotent(): void
    {
        Notification::fake();

        $adminA = User::factory()->create([
            'is_admin' => true,
        ]);
        $adminB = User::factory()->create([
            'is_admin' => true,
        ]);
        $nonAdmin = User::factory()->create();
        $registered = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $event = new UserRegistered(
            userId: (int) $registered->getKey(),
        );
        $listener = app(SendAdminUserRegisteredNotification::class);

        $listener->handle($event);
        $listener->handle($event);

        $assertRegistrationNotification = static fn (AdminUserRegisteredNotification $notification): bool => $notification->telegramTopic() === NotificationTopic::Account
            && $notification->via(new \stdClass()) === [TelegramChannel::class];

        Notification::assertSentToTimes(
            $adminA,
            AdminUserRegisteredNotification::class,
            1,
        );
        Notification::assertSentToTimes(
            $adminB,
            AdminUserRegisteredNotification::class,
            1,
        );
        Notification::assertSentTo(
            $adminA,
            AdminUserRegisteredNotification::class,
            $assertRegistrationNotification,
        );
        Notification::assertSentTo(
            $adminB,
            AdminUserRegisteredNotification::class,
            $assertRegistrationNotification,
        );
        Notification::assertNotSentTo(
            $nonAdmin,
            AdminUserRegisteredNotification::class,
        );
        Notification::assertNotSentTo(
            $registered,
            AdminUserRegisteredNotification::class,
        );

        $message = (new AdminUserRegisteredNotification(
            userId: (int) $registered->getKey(),
            phone: (string) $registered->phone,
        ))->toTelegram($adminA)->text;

        $this->assertStringContainsString(
            '091•••••789',
            $message,
        );
        $this->assertStringNotContainsString(
            '09123456789',
            $message,
        );
    }

    public function test_paid_payment_alert_targets_admins_only_and_is_not_duplicated_by_same_state_save(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $otherUser = User::factory()->create();
        $customer = User::factory()->create();
        $order = $this->createOrder($customer);
        $payment = $this->createPayment(
            $order,
            PaymentStatus::Pending,
        );

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'verified_at' => now(),
        ])->save();

        Notification::assertSentToTimes(
            $admin,
            AdminPaymentSucceededNotification::class,
            1,
        );
        Notification::assertNotSentTo(
            $customer,
            AdminPaymentSucceededNotification::class,
        );
        Notification::assertNotSentTo(
            $otherUser,
            AdminPaymentSucceededNotification::class,
        );

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
        ])->save();

        Notification::assertSentToTimes(
            $admin,
            AdminPaymentSucceededNotification::class,
            1,
        );
    }

    public function test_non_paid_payment_event_does_not_alert_admin(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'is_admin' => true,
        ]);
        $customer = User::factory()->create();
        $order = $this->createOrder($customer);
        $payment = $this->createPayment(
            $order,
            PaymentStatus::Pending,
        );

        app(SendAdminPaymentSucceededNotification::class)->handle(
            new PaymentStatusChanged(
                paymentId: (int) $payment->getKey(),
                orderId: (int) $order->getKey(),
                status: PaymentStatus::Failed,
            ),
        );

        Notification::assertNotSentTo(
            $admin,
            AdminPaymentSucceededNotification::class,
        );
    }

    private function createOrder(User $user): Order
    {
        return $user->orders()->create([
            'type' => OrderType::Provisioning,
            'cloud_provider' => CloudProviderType::Liara,
            'region_id' => 'iran',
            'size_id' => 'standard-base-g2',
            'image_id' => 'ubuntu-26.04',
            'image_name' => 'Ubuntu 26.04',
            'image_distribution' => 'ubuntu',
            'image_version' => '26.04',
            'default_disk_gib' => 20,
            'selected_disk_gib' => 20,
            'period' => '14_days',
            'duration_hours' => 336,
            'provider_cost' => 500_000,
            'markup_percent' => 75,
            'final_amount' => 875_000,
            'currency' => 'IRR',
            'status' => OrderStatus::PendingPayment,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => null,
        ]);
    }

    private function createPayment(
        Order $order,
        PaymentStatus $status,
    ): Payment {
        return $order->payments()->create([
            'gateway' => 'zarinpal',
            'amount' => $order->final_amount,
            'currency' => $order->currency,
            'status' => $status,
            'gateway_reference' => sprintf(
                'admin-alert-%s-%s',
                $order->getKey(),
                uniqid(),
            ),
            'verified_at' => null,
        ]);
    }
}
