<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Actions\ProvisionCloudServerAction;
use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class ProvisionCloudServerProviderBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_delivery_can_complete_without_ssh_readiness(): void
    {
        $user = User::factory()->create();
        $catalog = $this->catalog();
        $provisioner = Mockery::mock(CloudServerProvisionerInterface::class);

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
            ->with('eu-west1-a', 'provider-123')
            ->andReturn(
                $this->readyCloudServer(
                    id: 'provider-123',
                    name: 'xdeploy-order-123',
                ),
            );

        $result = $this->action(
            catalog: $catalog,
            provisioner: $provisioner,
        )->provisionProviderResource(
            user: $user,
            data: $this->data('xdeploy-order-123'),
        );

        $this->assertSame(ServerStatus::Inactive, $result->server->status);
        $this->assertSame('203.0.113.77', $result->server->host);
        $this->assertSame('temporary-password', $result->server->credential);
        $this->assertSame('provider-123', $result->server->cloud_server_id);
    }

    public function test_provider_credential_can_arrive_asynchronously_during_details_polling(): void
    {
        $user = User::factory()->create();
        $catalog = $this->catalog();
        $provisioner = Mockery::mock(CloudServerProvisionerInterface::class);

        $provisioner->shouldReceive('createServer')
            ->once()
            ->andReturn(
                new CreatedCloudServerData(
                    id: 'provider-async',
                    name: 'xdeploy-order-async',
                    regionId: 'eu-west1-a',
                    status: CloudServerStatus::Provisioning,
                    username: 'root',
                    createdAt: new DateTimeImmutable,
                    generatedPassword: null,
                ),
            );

        $ready = $this->readyCloudServer(
            id: 'provider-async',
            name: 'xdeploy-order-async',
            username: 'root',
            generatedPassword: 'delayed-password',
        );

        $this->assertArrayNotHasKey(
            'generated_password',
            $ready->toArray(),
        );
        $this->assertSame(
            '[REDACTED]',
            $ready->__debugInfo()['generated_password'],
        );

        $provisioner->shouldReceive('findServer')
            ->once()
            ->with('eu-west1-a', 'provider-async')
            ->andReturnUsing(function () use ($ready): CloudServerData {
                $persisted = Server::query()
                    ->where('cloud_server_id', 'provider-async')
                    ->first();

                $this->assertInstanceOf(Server::class, $persisted);
                $this->assertFalse($persisted->hasCredential());

                return $ready;
            });

        $result = $this->action(
            catalog: $catalog,
            provisioner: $provisioner,
        )->provisionProviderResource(
            user: $user,
            data: $this->data('xdeploy-order-async'),
        );

        $this->assertSame('203.0.113.77', $result->server->host);
        $this->assertTrue($result->server->hasCredential());
        $this->assertSame('delayed-password', $result->server->credential);
    }

    private function action(
        CloudProviderInterface $catalog,
        CloudServerProvisionerInterface $provisioner,
    ): ProvisionCloudServerAction {
        return new ProvisionCloudServerAction(
            catalog: $catalog,
            provisioner: $provisioner,
            createServer: app(CreateServerAction::class),
            verifySshReadiness: app(VerifyCloudServerSshReadinessAction::class),
            providerName: 'arvan',
            maxAttempts: 1,
            pollDelaySeconds: 0,
            providers: new CloudProviderRegistryStub(
                provider: $catalog,
                capabilities: [
                    CloudServerProvisionerInterface::class => $provisioner,
                ],
            ),
        );
    }

    private function catalog(): CloudProviderInterface
    {
        $catalog = Mockery::mock(CloudProviderInterface::class);

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

        return $catalog;
    }

    private function data(string $name): CreateCloudServerData
    {
        return new CreateCloudServerData(
            name: $name,
            regionId: 'eu-west1-a',
            sizeId: 'eco-2-2-0',
            imageId: 'ubuntu-24',
            diskGiB: 30,
        );
    }

    private function readyCloudServer(
        string $id,
        string $name,
        string $username = 'ubuntu',
        ?string $generatedPassword = null,
    ): CloudServerData {
        return new CloudServerData(
            id: $id,
            name: $name,
            regionId: 'eu-west1-a',
            status: CloudServerStatus::Active,
            username: $username,
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
            generatedPassword: $generatedPassword,
        );
    }
}
