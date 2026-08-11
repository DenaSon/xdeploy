<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Notifications;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use App\Listeners\SendCloudServerExpiringSoonSms;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CloudServerExpiringSoonSmsListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_expiring_event_sends_one_sms(): void
    {
        $provider = new class implements SmsProviderInterface
        {
            public int $expirationCalls = 0;

            public function sendVerificationCode(
                PhoneNumber $phone,
                OtpCode $code,
            ): void {}

            public function sendCloudServerExpirationWarning(
                PhoneNumber $phone,
            ): void {
                $this->expirationCalls++;
            }
        };

        $this->app->instance(
            SmsProviderInterface::class,
            $provider,
        );

        $user = User::factory()->create([
            'phone' => '09123456789',
        ]);

        $event = new CloudServerExpiringSoon(
            userId: (int) $user->getKey(),
            serverId: 42,
            serverName: 'Cloud VPS',
            expiresAt: now()
                ->addHours(23)
                ->toIso8601String(),
        );

        $listener = app(
            SendCloudServerExpiringSoonSms::class,
        );

        $listener->handle($event);
        $listener->handle($event);

        $this->assertSame(
            1,
            $provider->expirationCalls,
        );

        /** @var NotificationDelivery $delivery */
        $delivery = NotificationDelivery::query()
            ->sole();

        $this->assertSame(
            $event->dedupeKey().':sms',
            $delivery->dedupe_key,
        );

        $this->assertSame(
            $event->dedupeKey().':sms',
            $listener->uniqueId($event),
        );

        $this->assertSame(
            'provisioning',
            $listener->viaQueue(),
        );
    }
}
