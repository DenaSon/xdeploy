<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Notifications;

use App\Application\Notifications\Actions\SendNotificationOnceAction;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\Cloud\CloudServerExpiringSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SendNotificationOnceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_delivery_key_creates_only_one_visible_notification(): void
    {
        $user = User::factory()->create();

        $action = app(
            SendNotificationOnceAction::class,
        );

        $notification =
            new CloudServerExpiringSoonNotification(
                serverId: 42,
                serverName: 'Cloud VPS',
                expiresAt: now()
                    ->addHours(23)
                    ->toIso8601String(),
            );

        $first = $action->execute(
            user: $user,
            dedupeKey: 'cloud-server:42:expiring-24h:test',
            notification: $notification,
        );

        $second = $action->execute(
            user: $user,
            dedupeKey: 'cloud-server:42:expiring-24h:test',
            notification: $notification,
        );

        $this->assertTrue(
            $first,
        );

        $this->assertFalse(
            $second,
        );

        $this->assertSame(
            1,
            $user
                ->notifications()
                ->count(),
        );

        /** @var NotificationDelivery $delivery */
        $delivery =
            NotificationDelivery::query()
                ->sole();

        $this->assertSame(
            NotificationDelivery::STATUS_DELIVERED,
            $delivery->status,
        );

        $this->assertSame(
            1,
            $delivery->attempts,
        );

        $this->assertNotNull(
            $delivery->delivered_at,
        );

        $databaseNotification =
            $user
                ->notifications()
                ->firstOrFail();

        $this->assertSame(
            'cloud_server_expiring_soon',
            $databaseNotification->data['kind'],
        );
    }
}
