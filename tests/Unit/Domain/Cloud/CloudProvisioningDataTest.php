<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud;

use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerStatus;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CloudProvisioningDataTest extends TestCase
{
    public function test_cloud_server_status_helpers_are_correct(): void
    {
        $this->assertTrue(
            CloudServerStatus::Active->isReady(),
        );

        $this->assertTrue(
            CloudServerStatus::Provisioning->isPending(),
        );

        $this->assertTrue(
            CloudServerStatus::Failed->isFailed(),
        );

        $this->assertFalse(
            CloudServerStatus::Unknown->isReady(),
        );
    }

    public function test_create_data_detects_ssh_key_usage(): void
    {
        $passwordData = new CreateCloudServerData(
            name: 'xdeploy-server',
            regionId: 'eu-west1-a',
            sizeId: 'eco-1-1-0',
            imageId: 'ubuntu-image',
            networkId: 'network-id',
            securityGroupIds: [
                'security-group-id',
            ],
            diskGiB: 25,
        );

        $sshKeyData = new CreateCloudServerData(
            name: 'xdeploy-server',
            regionId: 'eu-west1-a',
            sizeId: 'eco-1-1-0',
            imageId: 'ubuntu-image',
            networkId: 'network-id',
            securityGroupIds: [
                'security-group-id',
            ],
            diskGiB: 25,
            sshKeyName: 'xdeploy-provisioner',
        );

        $this->assertFalse(
            $passwordData->usesSshKey(),
        );

        $this->assertTrue(
            $sshKeyData->usesSshKey(),
        );
    }

    public function test_it_validates_address_version(): void
    {
        $address = new CloudServerAddressData(
            address: '185.204.168.213',
            version: CloudIpVersion::IPv4,
            isPublic: true,
            isVpc: false,
            type: 'fixed',
        );

        $this->assertTrue(
            $address->isPublicIpv4(),
        );

        $this->assertFalse(
            $address->isPublicIpv6(),
        );
    }

    public function test_it_rejects_an_invalid_address(): void
    {
        $this->expectException(
            InvalidArgumentException::class,
        );

        new CloudServerAddressData(
            address: 'not-an-ip',
            version: CloudIpVersion::IPv4,
            isPublic: true,
            isVpc: false,
        );
    }

    public function test_it_extracts_unique_public_ipv4_addresses(): void
    {
        $server = new CloudServerData(
            id: 'server-id',
            name: 'xdeploy-server',
            regionId: 'eu-west1-a',
            status: CloudServerStatus::Active,
            username: 'ubuntu',
            sizeId: 'eco-1-1-0',
            imageId: 'ubuntu-image',
            createdAt: new DateTimeImmutable(
                '2026-08-04T10:00:00+03:30',
            ),
            addresses: [
                new CloudServerAddressData(
                    address: '185.204.168.213',
                    version: CloudIpVersion::IPv4,
                    isPublic: true,
                    isVpc: false,
                ),
                new CloudServerAddressData(
                    address: '185.204.168.213',
                    version: CloudIpVersion::IPv4,
                    isPublic: true,
                    isVpc: false,
                ),
                new CloudServerAddressData(
                    address: '10.0.0.10',
                    version: CloudIpVersion::IPv4,
                    isPublic: false,
                    isVpc: true,
                ),
            ],
        );

        $this->assertSame(
            [
                '185.204.168.213',
            ],
            $server->publicIpv4s(),
        );

        $this->assertSame(
            '185.204.168.213',
            $server->firstPublicIpv4(),
        );

        $this->assertTrue(
            $server->isReadyForSshCheck(),
        );
    }

    public function test_created_server_does_not_expose_password(): void
    {
        $server = new CreatedCloudServerData(
            id: 'server-id',
            name: 'xdeploy-server',
            regionId: 'eu-west1-a',
            status: CloudServerStatus::Provisioning,
            username: 'ubuntu',
            createdAt: new DateTimeImmutable(
                '2026-08-04T10:00:00+03:30',
            ),
            generatedPassword: 'sensitive-password',
        );

        $this->assertSame(
            'sensitive-password',
            $server->generatedPassword(),
        );

        $this->assertTrue(
            $server->hasGeneratedPassword(),
        );

        $this->assertArrayNotHasKey(
            'generated_password',
            $server->toArray(),
        );

        $this->assertSame(
            '[REDACTED]',
            $server->__debugInfo()[
            'generated_password'
            ],
        );
    }
}
