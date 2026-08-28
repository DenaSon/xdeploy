<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Application\Notifications\NotificationTopic;
use App\Application\Support\Actions\CreateSupportRequestAction;
use App\Application\Support\Events\SupportRequestCreated;
use App\Domain\Support\Enums\SupportMessageAuthorRole;
use App\Domain\Support\Enums\SupportRequestCategory;
use App\Domain\Support\Enums\SupportRequestStatus;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use App\Listeners\SendAdminSupportRequestCreatedNotification;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\Admin\AdminSupportRequestCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

final class AdminSupportRequestTelegramAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_request_created_event_is_emitted_after_request_is_persisted(): void
    {
        Event::fake([
            SupportRequestCreated::class,
        ]);

        $user = User::factory()->create();

        $supportRequest = app(CreateSupportRequestAction::class)->execute(
            user: $user,
            subject: 'مشکل در اتصال سرور',
            category: SupportRequestCategory::Technical,
            message: 'لطفاً این اتصال را بررسی کنید.',
        );

        $this->assertDatabaseHas(
            'support_requests',
            [
                'id' => $supportRequest->getKey(),
                'user_id' => $user->getKey(),
                'subject' => 'مشکل در اتصال سرور',
            ],
        );

        Event::assertDispatchedTimes(
            SupportRequestCreated::class,
            1,
        );
        Event::assertDispatched(
            SupportRequestCreated::class,
            static fn (SupportRequestCreated $event): bool => $event->supportRequestId === (int) $supportRequest->getKey(),
        );
    }

    public function test_support_request_creation_succeeds_when_admin_alert_dispatch_fails(): void
    {
        Event::forget(
            SupportRequestCreated::class,
        );
        Event::listen(
            SupportRequestCreated::class,
            static function (SupportRequestCreated $event): void {
                throw new RuntimeException(
                    'notification queue unavailable',
                );
            },
        );

        $user = User::factory()->create();

        $supportRequest = app(CreateSupportRequestAction::class)->execute(
            user: $user,
            subject: 'پیگیری درخواست',
            category: SupportRequestCategory::Account,
            message: 'این درخواست باید حتی با خرابی اعلان ذخیره شود.',
        );

        $this->assertDatabaseHas(
            'support_requests',
            [
                'id' => $supportRequest->getKey(),
                'user_id' => $user->getKey(),
            ],
        );
        $this->assertDatabaseHas(
            'support_messages',
            [
                'support_request_id' => $supportRequest->getKey(),
                'body' => 'این درخواست باید حتی با خرابی اعلان ذخیره شود.',
            ],
        );
    }

    public function test_support_request_alert_targets_admins_only_is_idempotent_and_excludes_message_body(): void
    {
        Notification::fake();

        $adminA = User::factory()->create([
            'is_admin' => true,
        ]);
        $adminB = User::factory()->create([
            'is_admin' => true,
        ]);
        $nonAdmin = User::factory()->create();
        $customer = User::factory()->create();

        $supportRequest = SupportRequest::query()->create([
            'user_id' => $customer->getKey(),
            'server_id' => null,
            'subject' => 'بررسی صورتحساب',
            'category' => SupportRequestCategory::Billing,
            'status' => SupportRequestStatus::Open,
            'last_message_at' => now(),
            'closed_at' => null,
        ]);

        $secretBody = 'SUPPORT-BODY-MUST-NOT-LEAK-4827';
        $supportRequest->messages()->create([
            'author_id' => $customer->getKey(),
            'author_role' => SupportMessageAuthorRole::User,
            'body' => $secretBody,
        ]);

        $event = new SupportRequestCreated(
            supportRequestId: (int) $supportRequest->getKey(),
        );
        $listener = app(SendAdminSupportRequestCreatedNotification::class);

        $this->assertSame(
            'notifications',
            $listener->viaQueue(),
        );

        $listener->handle($event);
        $listener->handle($event);

        $assertSupportNotification = static fn (AdminSupportRequestCreatedNotification $notification): bool => $notification->telegramTopic() === NotificationTopic::Support
            && $notification->via(new \stdClass()) === [TelegramChannel::class]
            && $notification->supportRequestId === (int) $supportRequest->getKey();

        Notification::assertSentToTimes(
            $adminA,
            AdminSupportRequestCreatedNotification::class,
            1,
        );
        Notification::assertSentToTimes(
            $adminB,
            AdminSupportRequestCreatedNotification::class,
            1,
        );
        Notification::assertSentTo(
            $adminA,
            AdminSupportRequestCreatedNotification::class,
            $assertSupportNotification,
        );
        Notification::assertSentTo(
            $adminB,
            AdminSupportRequestCreatedNotification::class,
            $assertSupportNotification,
        );
        Notification::assertNotSentTo(
            $nonAdmin,
            AdminSupportRequestCreatedNotification::class,
        );
        Notification::assertNotSentTo(
            $customer,
            AdminSupportRequestCreatedNotification::class,
        );

        $message = (new AdminSupportRequestCreatedNotification(
            supportRequestId: (int) $supportRequest->getKey(),
            userId: (int) $customer->getKey(),
            subject: (string) $supportRequest->subject,
            category: SupportRequestCategory::Billing,
        ))->toTelegram($adminA)->text;

        $this->assertStringContainsString(
            'بررسی صورتحساب',
            $message,
        );
        $this->assertStringContainsString(
            'مالی',
            $message,
        );
        $this->assertStringContainsString(
            '/admin/support/'.$supportRequest->getKey(),
            $message,
        );
        $this->assertStringNotContainsString(
            $secretBody,
            $message,
        );
    }
}
