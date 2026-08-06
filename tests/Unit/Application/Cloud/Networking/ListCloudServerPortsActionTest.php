<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Networking;

use App\Application\Cloud\Networking\ListCloudServerPortsAction;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\DTOs\CloudPortData;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use PHPUnit\Framework\TestCase;

final class ListCloudServerPortsActionTest extends TestCase
{
    public function test_it_returns_cloud_server_ports(): void
    {
        $expectedPorts = [
            new CloudPortData(
                id: 'port-123',
                serverId: 'server-123',
                ips: [
                    '203.0.113.10',
                ],
                macAddress: 'fa:16:3e:00:00:01',
                networkId: 'network-123',
                portSecurityEnabled: true,
                securityGroupIds: [
                    'security-group-123',
                ],
                status: 'ACTIVE',
            ),

            new CloudPortData(
                id: 'port-456',
                serverId: 'server-123',
                ips: [
                    '2001:db8::10',
                ],
                macAddress: 'fa:16:3e:00:00:02',
                networkId: 'network-456',
                portSecurityEnabled: true,
                securityGroupIds: [],
                status: 'ACTIVE',
            ),
        ];

        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn(
                $expectedPorts,
            );

        $action = new ListCloudServerPortsAction(
            networking: $networking,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            $expectedPorts,
            $result,
        );

        $this->assertCount(
            2,
            $result,
        );

        $this->assertSame(
            'port-123',
            $result[0]->id,
        );

        $this->assertSame(
            [
                '203.0.113.10',
            ],
            $result[0]->ips,
        );
    }

    public function test_it_returns_an_empty_list_when_server_has_no_ports(): void
    {
        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn([]);

        $action = new ListCloudServerPortsAction(
            networking: $networking,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            [],
            $result,
        );
    }

    public function test_it_does_not_hide_provider_exceptions(): void
    {
        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('listServerPorts')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willThrowException(
                new CloudConnectionException(
                    'Cloud provider is temporarily unavailable.',
                ),
            );

        $action = new ListCloudServerPortsAction(
            networking: $networking,
        );

        $this->expectException(
            CloudConnectionException::class,
        );

        $this->expectExceptionMessage(
            'Cloud provider is temporarily unavailable.',
        );

        $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );
    }
}
