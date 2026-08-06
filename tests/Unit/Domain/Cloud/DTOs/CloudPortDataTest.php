<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud\DTOs;

use App\Domain\Cloud\DTOs\CloudPortData;
use PHPUnit\Framework\TestCase;

final class CloudPortDataTest extends TestCase
{
    public function test_it_stores_port_information(): void
    {
        $port = new CloudPortData(
            id: 'port-123',
            serverId: 'server-456',
            ips: [
                '185.204.168.213',
                '2001:db8::1',
            ],
            networkId: 'network-789',
            macAddress: 'fa:16:3e:12:34:56',
            status: 'ACTIVE',
            portSecurityEnabled: true,
            securityGroupIds: [
                'security-group-1',
                'security-group-2',
            ],
        );

        self::assertSame('port-123', $port->id);
        self::assertSame('server-456', $port->serverId);

        self::assertSame(
            [
                '185.204.168.213',
                '2001:db8::1',
            ],
            $port->ips,
        );

        self::assertSame(
            'network-789',
            $port->networkId,
        );

        self::assertSame(
            'fa:16:3e:12:34:56',
            $port->macAddress,
        );

        self::assertSame('ACTIVE', $port->status);
        self::assertTrue($port->portSecurityEnabled);

        self::assertSame(
            [
                'security-group-1',
                'security-group-2',
            ],
            $port->securityGroupIds,
        );
    }

    public function test_it_detects_an_ip_belonging_to_the_port(): void
    {
        $port = new CloudPortData(
            id: 'port-123',
            serverId: 'server-456',
            ips: [
                '185.204.168.213',
                '185.204.171.249',
            ],
            networkId: 'network-789',
            macAddress: 'fa:16:3e:12:34:56',
            status: 'ACTIVE',
            portSecurityEnabled: true,
            securityGroupIds: [],
        );

        self::assertTrue(
            $port->containsIp('185.204.168.213'),
        );

        self::assertFalse(
            $port->containsIp('185.204.170.100'),
        );
    }

    public function test_ip_comparison_is_strict(): void
    {
        $port = new CloudPortData(
            id: 'port-123',
            serverId: 'server-456',
            ips: ['127.0.0.1'],
            networkId: 'network-789',
            macAddress: 'fa:16:3e:12:34:56',
            status: 'ACTIVE',
            portSecurityEnabled: false,
            securityGroupIds: [],
        );

        self::assertFalse(
            $port->containsIp('127.0.0.01'),
        );
    }
}
