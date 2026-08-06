<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud\Networking;

use App\Application\Cloud\Networking\DeleteCloudServerPortAction;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\DTOs\CloudPortData;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteCloudServerPortActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_port_without_changing_unrelated_host(): void
    {
        $user = $this->createUser(
            '09170000101',
        );

        $server = $this->createCloudServer(
            user: $user,
            host: '185.204.168.210',
        );

        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->with(
                'eu-west1-a',
                'provider-server-123',
            )
            ->willReturn([
                $this->port(
                    id: 'port-123',
                    ips: [
                        '185.204.168.211',
                    ],
                ),
            ]);

        $networking
            ->expects($this->once())
            ->method('deletePort')
            ->with(
                'eu-west1-a',
                'port-123',
            );

        $result = $this->action(
            $networking,
        )->handle(
            user: $user,
            serverId: (int) $server->getKey(),
            portId: 'port-123',
        );

        $this->assertSame(
            '185.204.168.210',
            $result->host,
        );
    }

    public function test_it_replaces_host_when_current_ip_is_deleted(): void
    {
        $user = $this->createUser(
            '09170000102',
        );

        $server = $this->createCloudServer(
            user: $user,
            host: '185.204.168.212',
        );

        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->willReturn([
                $this->port(
                    id: 'port-current',
                    ips: [
                        '185.204.168.212',
                    ],
                ),

                $this->port(
                    id: 'port-replacement',
                    ips: [
                        '185.204.168.213',
                    ],
                ),
            ]);

        $networking
            ->expects($this->once())
            ->method('deletePort')
            ->with(
                'eu-west1-a',
                'port-current',
            );

        $result = $this->action(
            $networking,
        )->handle(
            user: $user,
            serverId: (int) $server->getKey(),
            portId: 'port-current',
        );

        $this->assertSame(
            '185.204.168.213',
            $result->host,
        );

        $this->assertDatabaseHas(
            'servers',
            [
                'id' => $server->getKey(),
                'host' => '185.204.168.213',
            ],
        );
    }

    public function test_it_clears_host_when_deleted_port_contains_last_ip(): void
    {
        $user = $this->createUser(
            '09170000103',
        );

        $server = $this->createCloudServer(
            user: $user,
            host: '185.204.168.214',
        );

        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->willReturn([
                $this->port(
                    id: 'port-last',
                    ips: [
                        '185.204.168.214',
                    ],
                ),
            ]);

        $networking
            ->expects($this->once())
            ->method('deletePort')
            ->with(
                'eu-west1-a',
                'port-last',
            );

        $result = $this->action(
            $networking,
        )->handle(
            user: $user,
            serverId: (int) $server->getKey(),
            portId: 'port-last',
        );

        $this->assertNull(
            $result->host,
        );

        $this->assertDatabaseHas(
            'servers',
            [
                'id' => $server->getKey(),
                'host' => null,
            ],
        );
    }

    public function test_it_keeps_host_when_provider_deletion_fails(): void
    {
        $user = $this->createUser(
            '09170000104',
        );

        $server = $this->createCloudServer(
            user: $user,
            host: '185.204.168.215',
        );

        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->willReturn([
                $this->port(
                    id: 'port-123',
                    ips: [
                        '185.204.168.215',
                    ],
                ),
            ]);

        $networking
            ->expects($this->once())
            ->method('deletePort')
            ->with(
                'eu-west1-a',
                'port-123',
            )
            ->willThrowException(
                new CloudConnectionException(
                    'Cloud provider is temporarily unavailable.',
                ),
            );

        try {
            $this->action(
                $networking,
            )->handle(
                user: $user,
                serverId: (int) $server->getKey(),
                portId: 'port-123',
            );

            $this->fail(
                'Expected cloud deletion to fail.',
            );
        } catch (CloudConnectionException $exception) {
            $this->assertSame(
                'Cloud provider is temporarily unavailable.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(
            '185.204.168.215',
            $server->refresh()->host,
        );
    }

    public function test_it_rejects_port_not_belonging_to_server(): void
    {
        $user = $this->createUser(
            '09170000105',
        );

        $server = $this->createCloudServer(
            user: $user,
            host: '185.204.168.216',
        );

        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->willReturn([
                $this->port(
                    id: 'port-owned',
                    ips: [
                        '185.204.168.216',
                    ],
                ),
            ]);

        $networking
            ->expects($this->never())
            ->method('deletePort');

        $this->expectException(
            CloudResourceNotFoundException::class,
        );

        $this->expectExceptionMessage(
            'Cloud server port was not found.',
        );

        $this->action(
            $networking,
        )->handle(
            user: $user,
            serverId: (int) $server->getKey(),
            portId: 'foreign-port',
        );
    }

    public function test_it_rejects_server_owned_by_another_user(): void
    {
        $user = $this->createUser(
            '09170000106',
        );

        $otherUser = $this->createUser(
            '09170000107',
        );

        $foreignServer = $this->createCloudServer(
            user: $otherUser,
            host: '185.204.168.217',
        );

        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->never())
            ->method('listServerPorts');

        $networking
            ->expects($this->never())
            ->method('deletePort');

        $this->expectException(
            ModelNotFoundException::class,
        );

        $this->action(
            $networking,
        )->handle(
            user: $user,
            serverId: (int) $foreignServer->getKey(),
            portId: 'port-123',
        );
    }

    private function action(
        CloudServerNetworkingInterface $networking,
    ): DeleteCloudServerPortAction {
        return new DeleteCloudServerPortAction(
            networking: $networking,
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
        string $host,
    ): Server {
        $server = new Server([
            'name' => 'Cloud server',
            'host' => $host,
            'port' => 22,
            'username' => 'ubuntu',
        ]);

        $server->status = ServerStatus::Inactive;
        $server->cloud_provider = 'arvan';
        $server->cloud_server_id = 'provider-server-123';
        $server->cloud_region = 'eu-west1-a';
        $server->provisioned_at = now();

        $user->servers()->save(
            $server,
        );

        return $server->refresh();
    }

    /**
     * @param list<string> $ips
     */
    private function port(
        string $id,
        array $ips,
    ): CloudPortData {
        return new CloudPortData(
            id: $id,
            serverId: 'provider-server-123',
            ips: $ips,
            macAddress: 'fa:16:3e:00:00:01',
            networkId: 'network-123',
            portSecurityEnabled: true,
            securityGroupIds: [
                'security-group-123',
            ],
            status: 'ACTIVE',
        );
    }
}
