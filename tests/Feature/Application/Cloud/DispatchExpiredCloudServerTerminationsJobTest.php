<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Jobs\DispatchExpiredCloudServerTerminationsJob;
use App\Application\Cloud\Jobs\TerminateExpiredCloudServerJob;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class DispatchExpiredCloudServerTerminationsJobTest extends TestCase
{
    use RefreshDatabase;

    private int $hostSequence = 30;

    public function test_only_expired_cloud_servers_are_dispatched_for_termination(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $expired = $this->cloudServer(
            user: $user,
            cloudServerId: 'cloud-expired',
            expiresAt: now()->subMinute(),
        );

        $this->cloudServer(
            user: $user,
            cloudServerId: 'cloud-future',
            expiresAt: now()->addHour(),
        );

        $user
            ->servers()
            ->create([
                'name' => 'Manual server',
                'host' => '192.0.2.30',
                'port' => 22,
                'username' => 'root',
                'status' => ServerStatus::Active,
                'expires_at' => now()->subMinute(),
            ]);

        (new DispatchExpiredCloudServerTerminationsJob)
            ->handle();

        Queue::assertPushed(
            TerminateExpiredCloudServerJob::class,
            1,
        );

        Queue::assertPushed(
            TerminateExpiredCloudServerJob::class,
            static fn (
                TerminateExpiredCloudServerJob $job,
            ): bool => $job->serverId
                === $expired->getKey(),
        );
    }

    public function test_cloud_server_without_expiration_is_not_dispatched(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $this->cloudServer(
            user: $user,
            cloudServerId: 'cloud-no-expiry',
            expiresAt: null,
        );

        (new DispatchExpiredCloudServerTerminationsJob)
            ->handle();

        Queue::assertNotPushed(
            TerminateExpiredCloudServerJob::class,
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
                'host' => sprintf(
                    '203.0.113.%d',
                    $this->hostSequence++,
                ),
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
}
