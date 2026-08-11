<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class DeleteCloudServerActionTest extends TestCase
{
    use RefreshDatabase;

    private int $hostSequence = 10;

    public function test_it_deletes_cloud_server_from_provider_then_soft_deletes_local_record(): void
    {
        $user = $this->createUser(
            phone: '09170000001',
        );

        $server = $this->createCloudServer(
            user: $user,
            status: ServerStatus::Inactive,
            cloudServerId: 'cloud-server-123',
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer')
            ->with(
                'eu-west1-a',
                'cloud-server-123',
            )
            ->willReturnCallback(function () use ($server): void {
                /*
                 * During Provider deletion the local record must
                 * still be visible to normal Eloquent queries.
                 */
                $this->assertModelExists(
                    $server,
                );
            });

        $this->action(
            lifecycle: $lifecycle,
        )->handle(
            user: $user,
            serverId: (int) $server->getKey(),
        );

        $this->assertSoftDeleted(
            'servers',
            [
                'id' => $server->getKey(),
            ],
        );
    }

    public function test_it_keeps_local_record_when_provider_deletion_fails(): void
    {
        $user = $this->createUser(
            phone: '09170000002',
        );

        $server = $this->createCloudServer(
            user: $user,
            status: ServerStatus::Inactive,
            cloudServerId: 'cloud-server-124',
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer')
            ->with(
                'eu-west1-a',
                'cloud-server-124',
            )
            ->willThrowException(
                new CloudConnectionException(
                    'Cloud provider is temporarily unavailable.',
                ),
            );

        try {
            $this->action(
                lifecycle: $lifecycle,
            )->handle(
                user: $user,
                serverId: (int) $server->getKey(),
            );

            $this->fail(
                'Expected cloud provider deletion to fail.',
            );
        } catch (CloudConnectionException $exception) {
            $this->assertSame(
                'Cloud provider is temporarily unavailable.',
                $exception->getMessage(),
            );
        }

        $this->assertModelExists(
            $server,
        );
    }

    public function test_it_rejects_a_server_owned_by_another_user(): void
    {
        $user = $this->createUser(
            phone: '09170000003',
        );

        $otherUser = $this->createUser(
            phone: '09170000004',
        );

        $foreignServer = $this->createCloudServer(
            user: $otherUser,
            status: ServerStatus::Inactive,
            cloudServerId: 'cloud-server-125',
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->never())
            ->method('deleteServer');

        try {
            $this->action(
                lifecycle: $lifecycle,
            )->handle(
                user: $user,
                serverId: (int) $foreignServer->getKey(),
            );

            $this->fail(
                'Expected ownership validation to fail.',
            );
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }

        $this->assertModelExists(
            $foreignServer,
        );
    }

    public function test_it_rejects_a_server_without_cloud_metadata(): void
    {
        $user = $this->createUser(
            phone: '09170000005',
        );

        $manualServer = $this->createManualServer(
            user: $user,
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->never())
            ->method('deleteServer');

        try {
            $this->action(
                lifecycle: $lifecycle,
            )->handle(
                user: $user,
                serverId: (int) $manualServer->getKey(),
            );

            $this->fail(
                'Expected incomplete cloud metadata to be rejected.',
            );
        } catch (CloudValidationException $exception) {
            $this->assertSame(
                'Cloud server metadata is incomplete.',
                $exception->getMessage(),
            );
        }

        $this->assertModelExists(
            $manualServer,
        );
    }

    public function test_deleting_active_cloud_server_does_not_change_remaining_server_statuses(): void
    {
        $user = $this->createUser(
            phone: '09170000006',
        );

        $activeServer = $this->createCloudServer(
            user: $user,
            status: ServerStatus::Active,
            cloudServerId: 'cloud-server-126',
        );

        $olderInactiveServer = $this->createCloudServer(
            user: $user,
            status: ServerStatus::Inactive,
            cloudServerId: 'cloud-server-127',
        );

        $latestInactiveServer = $this->createCloudServer(
            user: $user,
            status: ServerStatus::Inactive,
            cloudServerId: 'cloud-server-128',
        );

        $lifecycle = $this->mockLifecycle();

        $lifecycle
            ->expects($this->once())
            ->method('deleteServer')
            ->with(
                'eu-west1-a',
                'cloud-server-126',
            );

        $this->action(
            lifecycle: $lifecycle,
        )->handle(
            user: $user,
            serverId: (int) $activeServer->getKey(),
        );

        $this->assertSoftDeleted(
            'servers',
            [
                'id' => $activeServer->getKey(),
            ],
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $olderInactiveServer->refresh()->status,
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $latestInactiveServer->refresh()->status,
        );

        $this->assertSame(
            0,
            $user->servers()->active()->count(),
        );
    }

    private function action(
        CloudServerLifecycleInterface $lifecycle,
    ): DeleteCloudServerAction {
        return new DeleteCloudServerAction(
            lifecycle: $lifecycle,
        );
    }

    private function mockLifecycle(): CloudServerLifecycleInterface&MockObject
    {
        return $this->createMock(
            CloudServerLifecycleInterface::class,
        );
    }

    private function createUser(
        string $phone,
    ): User {
        return User::query()->create([
            'phone' => $phone,
        ]);
    }

    private function createCloudServer(
        User $user,
        ServerStatus $status,
        string $cloudServerId,
    ): Server {
        $server = new Server([
            'name' => 'Cloud server',
            'host' => $this->nextHost(),
            'port' => 22,
            'username' => 'ubuntu',
        ]);

        $server->status = $status;
        $server->cloud_provider = 'arvan';
        $server->cloud_region = 'eu-west1-a';
        $server->cloud_server_id = $cloudServerId;
        $server->provisioned_at = now();

        $user->servers()->save(
            $server,
        );

        return $server->refresh();
    }

    private function createManualServer(
        User $user,
    ): Server {
        $server = new Server([
            'name' => 'Manual server',
            'host' => $this->nextHost(),
            'port' => 22,
            'username' => 'root',
        ]);

        $server->status = ServerStatus::Inactive;

        $user->servers()->save(
            $server,
        );

        return $server->refresh();
    }

    private function nextHost(): string
    {
        return sprintf(
            '192.0.2.%d',
            $this->hostSequence++,
        );
    }
}
