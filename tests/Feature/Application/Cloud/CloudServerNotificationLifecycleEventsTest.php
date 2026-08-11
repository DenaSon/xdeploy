<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Events\CloudServerExpired;
use App\Application\Cloud\Events\CloudServerTerminated;
use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Application\Cloud\Servers\TerminateExpiredCloudServerAction;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class CloudServerNotificationLifecycleEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_expiration_emits_expired_and_terminated_events(): void
    {
        Event::fake([
            CloudServerExpired::class,
            CloudServerTerminated::class,
        ]);

        $server =
            $this->expiredCloudServer();

        $lifecycle =
            $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer');

        $this->action(
            $lifecycle,
        )->execute(
            (int) $server->getKey(),
        );

        Event::assertDispatched(
            CloudServerExpired::class,
            static fn (
                CloudServerExpired $event,
            ): bool => $event->serverId
                === $server->getKey(),
        );

        Event::assertDispatched(
            CloudServerTerminated::class,
            static fn (
                CloudServerTerminated $event,
            ): bool => $event->serverId
                === $server->getKey(),
        );
    }

    public function test_expired_event_is_emitted_only_on_first_termination_attempt(): void
    {
        Event::fake([
            CloudServerExpired::class,
            CloudServerTerminated::class,
        ]);

        $server =
            $this->expiredCloudServer();

        $lifecycle =
            $this->mockLifecycle();

        $lifecycle
            ->expects($this->exactly(2))
            ->method('deleteServer')
            ->willThrowException(
                new CloudConnectionException(
                    'Provider unavailable.',
                ),
            );

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $this->action(
                    $lifecycle,
                )->execute(
                    (int) $server->getKey(),
                );
            } catch (CloudConnectionException) {
                // Expected.
            }
        }

        Event::assertDispatchedTimes(
            CloudServerExpired::class,
            1,
        );

        Event::assertNotDispatched(
            CloudServerTerminated::class,
        );
    }

    private function action(
        CloudServerLifecycleInterface $lifecycle,
    ): TerminateExpiredCloudServerAction {
        return new TerminateExpiredCloudServerAction(
            deleteCloudServer: new DeleteCloudServerAction(
                lifecycle: $lifecycle,
            ),
        );
    }

    private function mockLifecycle(): CloudServerLifecycleInterface&MockObject
    {
        return $this->createMock(
            CloudServerLifecycleInterface::class,
        );
    }

    private function expiredCloudServer(): Server
    {
        $user = User::factory()->create();

        return $user
            ->servers()
            ->create([
                'name' => 'Expired Cloud VPS',
                'host' => '203.0.113.80',
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Active,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => 'cloud-notification-test',
                'cloud_region' => 'eu-west1-a',
                'provisioned_at' => now()->subDays(2),
                'expires_at' => now()->subMinute(),
            ]);
    }
}
