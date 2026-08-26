<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Application\Notifications\NotificationPreferenceService;
use App\Application\Notifications\NotificationTopic;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\Billing\PaymentSucceededNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_payment_creates_one_customer_notification(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $payment = $this->createPayment(
            $order,
            PaymentStatus::Pending,
        );

        $payment->forceFill([
            'status' => PaymentStatus::Paid,
            'verified_at' => now(),
        ])->save();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);

        $notification = $user->notifications()->firstOrFail();

        $this->assertSame(
            'payment_succeeded',
            $notification->data['kind'] ?? null,
        );
        $this->assertSame(
            $order->getKey(),
            $notification->data['order_id'] ?? null,
        );
        $this->assertSame(
            route(
                'panel.orders.show',
                ['order' => $order->getKey()],
                false,
            ),
            $notification->data['action_url'] ?? null,
        );

        /* Saving the same terminal state must not emit a duplicate. */
        $payment->forceFill([
            'status' => PaymentStatus::Paid,
        ])->save();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('notification_deliveries', 1);
    }

    public function test_cancelled_and_failed_payments_create_distinct_notifications(): void
    {
        $user = User::factory()->create();
        $order = $this->createOrder($user);

        $cancelled = $this->createPayment(
            $order,
            PaymentStatus::Pending,
        );
        $cancelled->forceFill([
            'status' => PaymentStatus::Cancelled,
            'failure_code' => 'customer_cancelled',
        ])->save();

        $failed = $this->createPayment(
            $order,
            PaymentStatus::Initiating,
        );
        $failed->forceFill([
            'status' => PaymentStatus::Failed,
            'failure_code' => 'initiation_failed',
        ])->save();

        $kinds = $user->notifications()
            ->get()
            ->pluck('data')
            ->map(static fn (array $data): ?string => $data['kind'] ?? null)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            ['payment_cancelled', 'payment_failed'],
            $kinds,
        );
        $this->assertDatabaseCount('notification_deliveries', 2);
    }

    public function test_billing_notifications_use_database_and_telegram_channels(): void
    {
        $notification = new PaymentSucceededNotification(
            paymentId: 11,
            orderId: 22,
            amount: 1_250_000,
        );

        $this->assertSame(
            NotificationTopic::Billing,
            $notification->telegramTopic(),
        );
        $this->assertSame(
            ['database', TelegramChannel::class],
            $notification->via(new \stdClass()),
        );
    }

    public function test_billing_telegram_preference_is_enabled_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(
            app(NotificationPreferenceService::class)
                ->telegramEnabled(
                    $user,
                    NotificationTopic::Billing,
                ),
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
                'ref-%s-%s',
                $order->getKey(),
                uniqid(),
            ),
            'verified_at' => null,
        ]);
    }
}
