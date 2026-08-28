<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

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
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class ParsPackProvisioningPollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_parspack_can_become_ready_after_the_generic_twenty_attempt_window(): void
    {
        config()->set(
            'cloud.providers.parspack.provisioning.max_attempts',
            21,
        );
        config()->set(
            'cloud.providers.parspack.provisioning.poll_delay_seconds',
            0,
        );

        $catalog = Mockery::mock(CloudProviderInterface::class);
        $provisioner = Mockery::mock(CloudServerProvisionerInterface::class);

        $catalog
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                new CloudRegionData(
                    id: 'frankfurt',
                    displayName: 'Frankfurt',
                    country: 'Germany',
                    city: 'Frankfurt',
                    dataCenter: null,
                    canCreateServers: true,
                    isVisible: true,
                    supportsVolumeBacked: false,
                ),
            ]);

        $catalog
            ->shouldReceive('listSizes')
            ->once()
            ->with('frankfurt')
            ->andReturn([
                new CloudSizeData(
                    id: 'deVPS2',
                    name: 'deVPS2',
                    regionId: 'frankfurt',
                    vCpu: 1,
                    memoryMiB: 2048,
                    diskGiB: 40,
                    category: 'parspack',
                    hourlyPrice: null,
                    monthlyPrice: null,
                ),
            ]);

        $catalog
            ->shouldReceive('listImages')
            ->once()
            ->with('frankfurt')
            ->andReturn([
                new CloudImageData(
                    id: 'ubuntu24-cloudinit-qcow2',
                    name: 'Ubuntu 24.04',
                    regionId: 'frankfurt',
                    distribution: 'ubuntu',
                    version: '24.04',
                    architecture: 'x86_64',
                    minDiskGiB: null,
                    minMemoryMiB: null,
                    supportsSshKey: true,
                    supportsPassword: true,
                ),
            ]);

        $provisioner
            ->shouldReceive('createServer')
            ->once()
            ->andReturn(
                new CreatedCloudServerData(
                    id: 'a34a-4c03-5f73-1a14',
                    name: 'coreflare-parspack-polling',
                    regionId: 'frankfurt',
                    status: CloudServerStatus::Provisioning,
                    username: 'root',
                    createdAt: null,
                    generatedPassword: 'temporary-bootstrap-password',
                ),
            );

        $poll = 0;

        $provisioner
            ->shouldReceive('findServer')
            ->times(21)
            ->with('frankfurt', 'a34a-4c03-5f73-1a14')
            ->andReturnUsing(function () use (&$poll): CloudServerData {
                $poll++;

                return new CloudServerData(
                    id: 'a34a-4c03-5f73-1a14',
                    name: 'coreflare-parspack-polling',
                    regionId: 'frankfurt',
                    status: $poll === 21
                        ? CloudServerStatus::Active
                        : CloudServerStatus::Provisioning,
                    username: 'root',
                    sizeId: 'deVPS2',
                    imageId: 'ubuntu24-cloudinit-qcow2',
                    createdAt: null,
                    addresses: $poll === 21
                        ? [
                            new CloudServerAddressData(
                                address: '185.110.191.31',
                                version: CloudIpVersion::IPv4,
                                isPublic: true,
                                isVpc: false,
                                type: 'public',
                            ),
                        ]
                        : [],
                );
            });

        $action = new ProvisionCloudServerAction(
            catalog: $catalog,
            provisioner: $provisioner,
            createServer: $this->app->make(CreateServerAction::class),
            verifySshReadiness: $this->app->make(
                VerifyCloudServerSshReadinessAction::class,
            ),
            providerName: 'parspack',
        );

        $user = User::query()->create([
            'name' => 'ParsPack Polling User',
            'phone' => '+4915112345678',
        ]);

        $result = $action->provisionProviderResource(
            user: $user,
            data: new CreateCloudServerData(
                name: 'coreflare-parspack-polling',
                regionId: 'frankfurt',
                sizeId: 'deVPS2',
                imageId: 'ubuntu24-cloudinit-qcow2',
                diskGiB: 40,
            ),
            provider: CloudProviderType::ParsPack,
        );

        $this->assertSame(21, $result->pollAttempts);
        $this->assertSame('185.110.191.31', $result->server->host);
        $this->assertSame(
            'temporary-bootstrap-password',
            $result->server->credential,
        );
    }
}
