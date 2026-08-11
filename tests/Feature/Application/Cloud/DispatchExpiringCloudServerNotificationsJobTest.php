<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Application\Cloud\Jobs\DispatchExpiringCloudServerNotificationsJob;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class DispatchExpiringCloudServerNotificationsJobTest extends TestCase
{
    use RefreshDatabase;

    private int $hostSequence = 40;

    public function test_only_cloud_servers_inside_next_24_hours_emit_warning_event(): void
    {
        Event::fake([
            CloudServerExpiringSoon::class,
        ]);

        $user = User::factory()->create();

        $eligible = $this->cloudServer(
            user: $user,
            cloudServerId: 'cloud-eligible',
            expiresAt: now()->addHours(23),
        );

        $this->cloudServer(
            user: $user,
            cloudServerId: 'cloud-too-early',
            expiresAt: now()->addHours(25),
        );

        $this->cloudServer(
            user: $user,
            cloudServerId: 'cloud-expired',
            expiresAt: now()->subMinute(),
        );

        $user
            ->servers()
            ->create([
                'name' => 'Manual VPS',
                'host' => $this->nextHost(),
                'port' => 22,
                'username' => 'root',
                'status' => ServerStatus::Active,
                'expires_at' => now()->addHours(12),
            ]);

        (new DispatchExpiringCloudServerNotificationsJob)
            ->handle();

        Event::assertDispatchedTimes(
            CloudServerExpiringSoon::class,
            1,
        );

        Event::assertDispatched(
            CloudServerExpiringSoon::class,
            static fn (
                CloudServerExpiringSoon $event,
            ): bool => $event->serverId
                === $eligible->getKey(),
        );
    }

    private function cloudServer(
        User $user,
        string $cloudServerId,
        mixed $expiresAt,
    ): Server {
        return $user
            ->servers()
            ->create([
                'name' => $cloudServerId,
                'host' => $this->nextHost(),
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Active,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => $cloudServerId,
                'cloud_region' => 'eu-west1-a',
                'provisioned_at' => now()->subDay(),
                'expires_at' => $expiresAt,
            ]);
    }

    private function nextHost(): string
    {
        return sprintf(
            '203.0.113.%d',
            $this->hostSequence++,
        );
    }
}
