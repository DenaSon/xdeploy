<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Networking;

use App\Application\Cloud\Networking\AddCloudServerPublicIpAction;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use PHPUnit\Framework\TestCase;

final class AddCloudServerPublicIpActionTest extends TestCase
{
    public function test_it_delegates_public_ip_creation_to_networking_provider(): void
    {
        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('addPublicIp')
            ->with(
                'eu-west1-a',
                'server-123',
                CloudIpVersion::IPv4,
                [
                    'security-group-123',
                    'security-group-456',
                ],
            );

        $action = new AddCloudServerPublicIpAction(
            networking: $networking,
        );

        $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
            version: CloudIpVersion::IPv4,
            securityGroupIds: [
                'security-group-123',
                'security-group-456',
            ],
        );
    }

    public function test_it_uses_ipv4_and_empty_security_groups_by_default(): void
    {
        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('addPublicIp')
            ->with(
                'eu-west1-a',
                'server-123',
                CloudIpVersion::IPv4,
                [],
            );

        $action = new AddCloudServerPublicIpAction(
            networking: $networking,
        );

        $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );
    }

    public function test_it_supports_ipv6(): void
    {
        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('addPublicIp')
            ->with(
                'eu-west1-a',
                'server-123',
                CloudIpVersion::IPv6,
                [],
            );

        $action = new AddCloudServerPublicIpAction(
            networking: $networking,
        );

        $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
            version: CloudIpVersion::IPv6,
        );
    }

    public function test_it_does_not_hide_provider_exceptions(): void
    {
        $networking = $this->createMock(
            CloudServerNetworkingInterface::class,
        );

        $networking
            ->expects($this->once())
            ->method('addPublicIp')
            ->with(
                'eu-west1-a',
                'server-123',
                CloudIpVersion::IPv4,
                [],
            )
            ->willThrowException(
                new CloudConnectionException(
                    'Cloud provider is temporarily unavailable.',
                ),
            );

        $action = new AddCloudServerPublicIpAction(
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
