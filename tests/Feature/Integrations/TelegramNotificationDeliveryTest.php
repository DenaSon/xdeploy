<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Application\Integrations\Telegram\Jobs\DeliverTelegramNotification;
use App\Application\Notifications\NotificationPreferenceService;
use App\Application\Notifications\NotificationTopic;
use App\Infrastructure\Integrations\Telegram\TelegramBotClient;
use App\Infrastructure\Integrations\Telegram\TelegramBotException;
use App\Infrastructure\Integrations\Telegram\TelegramChannel;
use App\Models\TelegramConnection;
use App\Models\User;
use App\Notifications\Cloud\CloudServerExpiringSoonNotification;
use App\Notifications\Cloud\CloudServerTerminatedNotification;
use App\Notifications\Cloud\CloudServerTerminationFailedNotification;
use App\Notifications\Profile\ProfileCompletionRequiredNotification;
use App\Notifications\Support\SupportRequestAnsweredNotification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TelegramNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://coreflare.test',
            'services.telegram.enabled' => true,
            'services.telegram.bot_token' => '123456:ci-telegram-bot-token',
            'services.telegram.bot_username' => 'CoreflareTestBot',
            'services.telegram.webhook_secret' => 'ci_telegram_webhook_secret_123',
            'services.telegram.link_ttl_seconds' => 600,
            'services.telegram.api_base_url' => 'https://api.telegram.test',
            'services.telegram.connect_timeout' => 5,
            'services.telegram.timeout' => 10,
        ]);
    }

    public function test_supported_notifications_keep_database_and_add_telegram_channel(): void
    {
        $user = User::factory()->create();
        $notifications = [
            new CloudServerExpiringSoonNotification(1, 'VPS-1', now()->addDay()->toIso8601String()),
            new CloudServerTerminatedNotification(1, 'VPS-1', now()->subDay()->toIso8601String(), now()->toIso8601String()),
            new CloudServerTerminationFailedNotification(1, 'VPS-1', now()->subDay()->toIso8601String(), 3),
            new ProfileCompletionRequiredNotification,
            new SupportRequestAnsweredNotification(1, 'درخواست آزمایشی'),
        ];

        foreach ($notifications as $notification) {
            self::assertSame(
                ['database', TelegramChannel::class],
                $notification->via($user),
            );
        }

        self::assertSame(NotificationTopic::Servers, $notifications[0]->telegramTopic());
        self::assertSame(NotificationTopic::Servers, $notifications[1]->telegramTopic());
        self::assertSame(NotificationTopic::Servers, $notifications[2]->telegramTopic());
        self::assertSame(NotificationTopic::Account, $notifications[3]->telegramTopic());
        self::assertSame(NotificationTopic::Support, $notifications[4]->telegramTopic());
    }

    public function test_connected_user_gets_database_notification_and_encrypted_telegram_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->connectTelegram($user, '123456789');

        $user->notify(new ProfileCompletionRequiredNotification);

        self::assertDatabaseCount('notifications', 1);

        Queue::assertPushed(
            DeliverTelegramNotification::class,
            function (DeliverTelegramNotification $job) use ($user): bool {
                self::assertSame($user->getKey(), $job->userId);
                self::assertSame(NotificationTopic::Account, $job->topic);
                self::assertSame('notifications', $job->queue);
                self::assertInstanceOf(ShouldBeEncrypted::class, $job);
                self::assertStringContainsString('پروفایل خود را تکمیل کنید', $job->text);
                self::assertStringContainsString('https://coreflare.test/panel/profile', $job->text);
                self::assertStringNotContainsString('123456789', serialize($job));

                return true;
            },
        );
    }

    public function test_disabled_topic_keeps_database_notification_without_telegram_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->connectTelegram($user, '123456789');

        app(NotificationPreferenceService::class)->setTelegramPreference(
            $user,
            NotificationTopic::Account,
            false,
        );

        $user->notify(new ProfileCompletionRequiredNotification);

        self::assertDatabaseCount('notifications', 1);
        Queue::assertNotPushed(DeliverTelegramNotification::class);
    }

    public function test_disconnected_user_keeps_database_notification_without_telegram_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $user->notify(new ProfileCompletionRequiredNotification);

        self::assertDatabaseCount('notifications', 1);
        Queue::assertNotPushed(DeliverTelegramNotification::class);
    }

    public function test_disabled_telegram_keeps_database_notification_without_queueing_delivery(): void
    {
        Queue::fake();

        config(['services.telegram.enabled' => false]);

        $user = User::factory()->create();
        $this->connectTelegram($user, '123456789');

        $user->notify(new ProfileCompletionRequiredNotification);

        self::assertDatabaseCount('notifications', 1);
        Queue::assertNotPushed(DeliverTelegramNotification::class);
    }

    public function test_delivery_job_resolves_current_connection_and_preference_at_execution_time(): void
    {
        $user = User::factory()->create();
        $this->connectTelegram($user, '123456789');

        Http::fake([
            'https://api.telegram.test/*' => Http::response(['ok' => true], 200),
        ]);

        $job = new DeliverTelegramNotification(
            (int) $user->getKey(),
            NotificationTopic::Servers,
            'پیام صف آزمایشی',
        );

        $job->handle(
            app(TelegramBotClient::class),
            app(NotificationPreferenceService::class),
        );

        Http::assertSent(
            static fn ($request): bool => $request['chat_id'] === '123456789'
                && $request['text'] === 'پیام صف آزمایشی',
        );
    }

    public function test_disabling_topic_before_job_execution_turns_delivery_into_noop(): void
    {
        $user = User::factory()->create();
        $this->connectTelegram($user, '123456789');

        $job = new DeliverTelegramNotification(
            (int) $user->getKey(),
            NotificationTopic::Support,
            'این پیام نباید ارسال شود',
        );

        app(NotificationPreferenceService::class)->setTelegramPreference(
            $user,
            NotificationTopic::Support,
            false,
        );

        Http::fake();

        $job->handle(
            app(TelegramBotClient::class),
            app(NotificationPreferenceService::class),
        );

        Http::assertNothingSent();
    }

    public function test_disconnect_before_job_execution_turns_delivery_into_noop(): void
    {
        $user = User::factory()->create();
        $this->connectTelegram($user, '123456789');

        $job = new DeliverTelegramNotification(
            (int) $user->getKey(),
            NotificationTopic::Account,
            'این پیام نباید ارسال شود',
        );

        TelegramConnection::query()
            ->where('user_id', $user->getKey())
            ->delete();

        Http::fake();

        $job->handle(
            app(TelegramBotClient::class),
            app(NotificationPreferenceService::class),
        );

        Http::assertNothingSent();
    }

    public function test_delivery_failure_bubbles_as_sanitized_exception_for_queue_retry(): void
    {
        $user = User::factory()->create();
        $this->connectTelegram($user, '123456789');

        Http::fake([
            'https://api.telegram.test/*' => Http::response([
                'ok' => false,
                'description' => 'private Telegram error details',
            ], 500),
        ]);

        $job = new DeliverTelegramNotification(
            (int) $user->getKey(),
            NotificationTopic::Servers,
            'پیام حساس آزمایشی',
        );

        try {
            $job->handle(
                app(TelegramBotClient::class),
                app(NotificationPreferenceService::class),
            );
            self::fail('Expected TelegramBotException was not thrown.');
        } catch (TelegramBotException $exception) {
            self::assertSame('Telegram message delivery failed.', $exception->getMessage());
            self::assertStringNotContainsString('private Telegram error details', $exception->getMessage());
            self::assertStringNotContainsString('123456789', $exception->getMessage());
            self::assertStringNotContainsString('پیام حساس آزمایشی', $exception->getMessage());
        }
    }

    private function connectTelegram(
        User $user,
        string $telegramId,
    ): TelegramConnection {
        return TelegramConnection::query()->create([
            'user_id' => $user->getKey(),
            'chat_id' => $telegramId,
            'telegram_user_id' => $telegramId,
            'username' => 'coreflare_test',
            'first_name' => 'Test',
            'connected_at' => now(),
        ]);
    }
}
