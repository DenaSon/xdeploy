<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Events\CloudServerTerminationFailed;
use App\Application\Cloud\Jobs\TerminateExpiredCloudServerJob;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

final class CloudServerTerminationFailedEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_job_failure_emits_one_user_facing_failure_event(): void
    {
        Event::fake([
            CloudServerTerminationFailed::class,
        ]);

        $user = User::factory()->create();

        $server = $user
            ->servers()
            ->create([
                'name' => 'Expired Cloud VPS',
                'host' => '203.0.113.81',
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Inactive,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => 'cloud-failed-test',
                'cloud_region' => 'eu-west1-a',
                'provisioned_at' => now()->subDays(2),
                'expires_at' => now()->subDay(),
                'termination_started_at' => now()->subHour(),
                'termination_last_attempt_at' => now(),
                'termination_attempts' => 5,
                'termination_last_error' => 'Provider unavailable.',
            ]);

        (new TerminateExpiredCloudServerJob(
            (int) $server->getKey(),
        ))->failed(
            new RuntimeException(
                'Provider unavailable.',
            ),
        );

        Event::assertDispatchedTimes(
            CloudServerTerminationFailed::class,
            1,
        );

        Event::assertDispatched(
            CloudServerTerminationFailed::class,
            static fn (
                CloudServerTerminationFailed $event,
            ): bool => $event->serverId
                === $server->getKey()
                && $event->attempts === 5,
        );
    }
}
