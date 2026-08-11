<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Notifications;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Listeners\SendCloudServerExpiringSoonNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CloudNotificationListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_expiring_event_is_deduplicated_by_listener_delivery_action(): void
    {
        $user = User::factory()->create();

        $event =
            new CloudServerExpiringSoon(
                userId: (int) $user->getKey(),

                serverId: 42,

                serverName: 'Cloud VPS',

                expiresAt: now()
                    ->addHours(23)
                    ->toIso8601String(),
            );

        $listener = app(
            SendCloudServerExpiringSoonNotification::class,
        );

        $listener->handle(
            $event,
        );

        $listener->handle(
            $event,
        );

        $this->assertSame(
            1,
            $user
                ->notifications()
                ->count(),
        );
    }
}
