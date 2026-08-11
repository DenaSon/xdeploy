<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Notifications;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Application\Notifications\Actions\SendCloudServerExpiringSoonSmsAction;
use App\Application\Notifications\Actions\SendNotificationOnceAction;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use App\Infrastructure\Sms\Exceptions\SmsSendingException;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\Cloud\CloudServerExpiringSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SendCloudServerExpiringSoonSmsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_sms_delivery_key_sends_only_once(): void
    {
        $provider = $this->fakeProvider();

        $this->app->instance(
            SmsProviderInterface::class,
            $provider,
        );

        $user = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $action = app(
            SendCloudServerExpiringSoonSmsAction::class,
        );

        $key = 'cloud-server:42:expiring-24h:test:sms';

        $this->assertTrue(
            $action->execute(
                user: $user,
                dedupeKey: $key,
            ),
        );

        $this->assertFalse(
            $action->execute(
                user: $user,
                dedupeKey: $key,
            ),
        );

        $this->assertSame(
            1,
            $provider->expirationCalls,
        );

        /** @var NotificationDelivery $delivery */
        $delivery = NotificationDelivery::query()
            ->sole();

        $this->assertSame(
            'sms',
            $delivery->channel,
        );

        $this->assertSame(
            CloudServerExpiringSoon::class,
            $delivery->notification_type,
        );

        $this->assertSame(
            NotificationDelivery::STATUS_DELIVERED,
            $delivery->status,
        );

        $this->assertSame(
            1,
            $delivery->attempts,
        );
    }

    public function test_database_and_sms_deliveries_are_deduplicated_independently(): void
    {
        $provider = $this->fakeProvider();

        $this->app->instance(
            SmsProviderInterface::class,
            $provider,
        );

        $user = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $baseKey = 'cloud-server:42:expiring-24h:independent';

        app(SendNotificationOnceAction::class)
            ->execute(
                user: $user,
                dedupeKey: $baseKey,
                notification: new CloudServerExpiringSoonNotification(
                    serverId: 42,
                    serverName: 'Cloud VPS',
                    expiresAt: now()
                        ->addHours(23)
                        ->toIso8601String(),
                ),
            );

        app(SendCloudServerExpiringSoonSmsAction::class)
            ->execute(
                user: $user,
                dedupeKey: $baseKey.':sms',
            );

        $this->assertSame(
            2,
            NotificationDelivery::query()->count(),
        );

        $this->assertSame(
            1,
            NotificationDelivery::query()
                ->where('channel', 'database')
                ->count(),
        );

        $this->assertSame(
            1,
            NotificationDelivery::query()
                ->where('channel', 'sms')
                ->count(),
        );

        $this->assertSame(
            1,
            $provider->expirationCalls,
        );
    }

    public function test_transient_failures_retry_up_to_persistent_attempt_limit(): void
    {
        $provider = $this->fakeProvider(
            transientFailures: 3,
        );

        $this->app->instance(
            SmsProviderInterface::class,
            $provider,
        );

        $user = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $action = app(
            SendCloudServerExpiringSoonSmsAction::class,
        );

        $key = 'cloud-server:42:expiring-24h:retry-limit:sms';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $action->execute(
                    user: $user,
                    dedupeKey: $key,
                );
            } catch (SmsSendingException $exception) {
                $this->assertTrue(
                    $exception->isRetryable(),
                );
            }
        }

        $this->assertFalse(
            $action->execute(
                user: $user,
                dedupeKey: $key,
            ),
        );

        $this->assertSame(
            3,
            $provider->expirationCalls,
        );

        /** @var NotificationDelivery $delivery */
        $delivery = NotificationDelivery::query()
            ->sole();

        $this->assertSame(
            NotificationDelivery::STATUS_FAILED,
            $delivery->status,
        );

        $this->assertSame(
            3,
            $delivery->attempts,
        );
    }

    public function test_permanent_failure_is_not_retried_by_later_duplicate_events(): void
    {
        $provider = $this->fakeProvider(
            permanentFailure: true,
        );

        $this->app->instance(
            SmsProviderInterface::class,
            $provider,
        );

        $user = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $action = app(
            SendCloudServerExpiringSoonSmsAction::class,
        );

        $key = 'cloud-server:42:expiring-24h:permanent:sms';

        try {
            $action->execute(
                user: $user,
                dedupeKey: $key,
            );
        } catch (SmsSendingException $exception) {
            $this->assertFalse(
                $exception->isRetryable(),
            );
        }

        $this->assertFalse(
            $action->execute(
                user: $user,
                dedupeKey: $key,
            ),
        );

        $this->assertSame(
            1,
            $provider->expirationCalls,
        );

        /** @var NotificationDelivery $delivery */
        $delivery = NotificationDelivery::query()
            ->sole();

        $this->assertSame(
            NotificationDelivery::STATUS_FAILED_PERMANENT,
            $delivery->status,
        );

        $this->assertSame(
            1,
            $delivery->attempts,
        );
    }

    private function fakeProvider(
        int $transientFailures = 0,
        bool $permanentFailure = false,
    ): SmsProviderInterface {
        return new class($transientFailures, $permanentFailure) implements SmsProviderInterface
        {
            public int $expirationCalls = 0;

            public function __construct(
                private int $transientFailures,
                private readonly bool $permanentFailure,
            ) {}

            public function sendVerificationCode(
                PhoneNumber $phone,
                OtpCode $code,
            ): void {}

            public function sendCloudServerExpirationWarning(
                PhoneNumber $phone,
            ): void {
                $this->expirationCalls++;

                if ($this->permanentFailure) {
                    throw SmsSendingException::permanent(
                        'SMS provider rejected the request.',
                    );
                }

                if ($this->transientFailures > 0) {
                    $this->transientFailures--;

                    throw SmsSendingException::transient(
                        'SMS provider is temporarily unavailable.',
                    );
                }
            }
        };
    }
}
