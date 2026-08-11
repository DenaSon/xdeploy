<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Application\Cloud\Servers\TerminateExpiredCloudServerAction;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class TerminateExpiredCloudServerActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_cloud_server_is_deleted_from_provider_then_soft_deleted_locally(): void
    {
        $server = $this->cloudServer(
            expiresAt: now()->subMinute(),
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer')
            ->with(
                'eu-west1-a',
                'cloud-server-123',
            );

        $terminated = $this->action(
            $lifecycle,
        )->execute(
            (int) $server->getKey(),
        );

        $this->assertTrue(
            $terminated,
        );

        $terminatedServer = Server::withTrashed()
            ->findOrFail(
                $server->getKey(),
            );

        $this->assertNotNull(
            $terminatedServer->deleted_at,
        );

        $this->assertNotNull(
            $terminatedServer->terminated_at,
        );

        $this->assertNotNull(
            $terminatedServer->termination_started_at,
        );

        $this->assertNotNull(
            $terminatedServer->termination_last_attempt_at,
        );

        $this->assertSame(
            1,
            $terminatedServer->termination_attempts,
        );

        $this->assertNull(
            $terminatedServer->termination_last_error,
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $terminatedServer->status,
        );
    }

    public function test_provider_not_found_is_treated_as_successful_termination(): void
    {
        $server = $this->cloudServer(
            expiresAt: now()->subMinute(),
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer')
            ->willThrowException(
                new CloudResourceNotFoundException(
                    'Cloud resource does not exist.',
                ),
            );

        $this->assertTrue(
            $this->action(
                $lifecycle,
            )->execute(
                (int) $server->getKey(),
            ),
        );

        $this->assertSoftDeleted(
            'servers',
            [
                'id' => $server->getKey(),
            ],
        );
    }

    public function test_provider_failure_keeps_local_server_for_retry_and_records_audit_data(): void
    {
        $server = $this->cloudServer(
            expiresAt: now()->subMinute(),
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer')
            ->willThrowException(
                new CloudConnectionException(
                    'Provider temporarily unavailable.',
                ),
            );

        try {
            $this->action(
                $lifecycle,
            )->execute(
                (int) $server->getKey(),
            );

            $this->fail(
                'Expected provider deletion failure.',
            );
        } catch (CloudConnectionException $exception) {
            $this->assertSame(
                'Provider temporarily unavailable.',
                $exception->getMessage(),
            );
        }

        $fresh = $server->fresh();

        $this->assertNotNull(
            $fresh,
        );

        $this->assertNull(
            $fresh->deleted_at,
        );

        $this->assertNull(
            $fresh->terminated_at,
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $fresh->status,
        );

        $this->assertSame(
            1,
            $fresh->termination_attempts,
        );

        $this->assertSame(
            'Provider temporarily unavailable.',
            $fresh->termination_last_error,
        );
    }

    public function test_server_before_expiration_is_a_noop(): void
    {
        $server = $this->cloudServer(
            expiresAt: now()->addHour(),
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->never())
            ->method('deleteServer');

        $this->assertFalse(
            $this->action(
                $lifecycle,
            )->execute(
                (int) $server->getKey(),
            ),
        );

        $this->assertModelExists(
            $server,
        );
    }

    public function test_user_provided_server_is_never_terminated_by_cloud_expiration(): void
    {
        $user = User::factory()->create();

        $server = $user
            ->servers()
            ->create([
                'name' => 'Manual VPS',
                'host' => '192.0.2.20',
                'port' => 22,
                'username' => 'root',
                'status' => ServerStatus::Active,
                'expires_at' => now()->subMinute(),
            ]);

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->never())
            ->method('deleteServer');

        $this->assertFalse(
            $this->action(
                $lifecycle,
            )->execute(
                (int) $server->getKey(),
            ),
        );

        $this->assertModelExists(
            $server,
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

    private function cloudServer(
        mixed $expiresAt,
    ): Server {
        $user = User::factory()->create();

        return $user
            ->servers()
            ->create([
                'name' => 'Expiring Cloud Server',
                'host' => '203.0.113.20',
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Active,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => 'cloud-server-123',
                'cloud_region' => 'eu-west1-a',
                'provisioned_at' => now()->subDay(),
                'expires_at' => $expiresAt,
            ]);
    }
}
