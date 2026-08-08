<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class ProvisionCloudServerProviderBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_delivery_can_complete_without_ssh_readiness(): void
    {
        $user = User::factory()->create();

        $catalog = Mockery::mock(
            CloudProviderInterface::class,
        );

        $catalog->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                new CloudRegionData(
                    id: 'eu-west1-a',
                    displayName: 'EU West',
                    country: null,
                    city: null,
                    dataCenter: null,
                    canCreateServers: true,
                    isVisible: true,
                    supportsVolumeBacked: true,
                ),
            ]);

        $catalog->shouldReceive('listSizes')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudSizeData(
                    id: 'eco-2-2-0',
                    name: 'eco-2-2-0',
                    regionId: 'eu-west1-a',
                    vCpu: 2,
                    memoryMiB: 2048,
                    diskGiB: 30,
                    category: null,
                    hourlyPrice: null,
                    monthlyPrice: null,
                ),
            ]);

        $catalog->shouldReceive('listImages')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudImageData(
                    id: 'ubuntu-24',
                    name: 'Ubuntu 24.04',
                    regionId: 'eu-west1-a',
                    distribution: 'Ubuntu',
                    version: '24.04',
                    architecture: null,
                    minDiskGiB: null,
                    minMemoryMiB: null,
                    supportsSshKey: true,
                    supportsPassword: true,
                ),
            ]);

        $catalog->shouldReceive('listNetworks')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudNetworkData(
                    id: 'network-default',
                    name: 'default',
                    regionId: 'eu-west1-a',
                    ipVersion: CloudIpVersion::IPv4,
                    cidr: null,
                    gateway: null,
                    isActive: true,
                    dhcpEnabled: true,
                ),
            ]);

        $catalog->shouldReceive('listSecurityGroups')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudSecurityGroupData(
                    id: 'security-default',
                    name: 'default',
                    regionId: 'eu-west1-a',
                    description: null,
                    isDefault: true,
                    isReadOnly: false,
                ),
            ]);

        $provisioner = Mockery::mock(
            CloudServerProvisionerInterface::class,
        );

        $provisioner->shouldReceive('createServer')
            ->once()
            ->andReturn(
                new CreatedCloudServerData(
                    id: 'provider-123',
                    name: 'xdeploy-order-123',
                    regionId: 'eu-west1-a',
                    status: CloudServerStatus::Provisioning,
                    username: 'ubuntu',
                    createdAt: new DateTimeImmutable,
                    generatedPassword: 'temporary-password',
                ),
            );

        $provisioner->shouldReceive('findServer')
            ->once()
            ->with(
                'eu-west1-a',
                'provider-123',
            )
            ->andReturn(
                new CloudServerData(
                    id: 'provider-123',
                    name: 'xdeploy-order-123',
                    regionId: 'eu-west1-a',
                    status: CloudServerStatus::Active,
                    username: 'ubuntu',
                    sizeId: 'eco-2-2-0',
                    imageId: 'ubuntu-24',
                    createdAt: new DateTimeImmutable,
                    addresses: [
                        new CloudServerAddressData(
                            address: '203.0.113.77',
                            version: CloudIpVersion::IPv4,
                            isPublic: true,
                            isVpc: false,
                        ),
                    ],
                ),
            );

        $action = new ProvisionCloudServerAction(
            catalog: $catalog,
            provisioner: $provisioner,
            createServer: app(CreateServerAction::class),
            verifySshReadiness: app(
                VerifyCloudServerSshReadinessAction::class,
            ),
            providerName: 'arvan',
            maxAttempts: 1,
            pollDelaySeconds: 0,
        );

        $result = $action->provisionProviderResource(
            user: $user,
            data: new CreateCloudServerData(
                name: 'xdeploy-order-123',
                regionId: 'eu-west1-a',
                sizeId: 'eco-2-2-0',
                imageId: 'ubuntu-24',
                networkId: 'network-default',
                securityGroupIds: [
                    'security-default',
                ],
                diskGiB: 30,
            ),
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $result->server->status,
        );

        $this->assertSame(
            '203.0.113.77',
            $result->server->host,
        );

        $this->assertSame(
            'provider-123',
            $result->server->cloud_server_id,
        );
    }
}
