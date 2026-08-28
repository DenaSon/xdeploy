<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\BuildCloudServerDataFromOrderAction;
use App\Application\Billing\Actions\ProvisionPaidOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerBootstrapCredentialSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerBootstrapCredentialData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Server\Enums\AuthenticationType;
use App\Models\Order;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Support\CloudProviderRegistryStub;
use Tests\TestCase;

final class ProvisionPaidOrderParsPackRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_adopts_parspack_resource_with_bootstrap_ssh_key_after_local_persistence_failure(): void
    {
        $user = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $user->id,
            'region_id' => 'frankfurt',
            'size_id' => 'deVPS2',
            'image_id' => 'ubuntu24-cloudinit-qcow2',
            'image_name' => 'Ubuntu 24.04',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 40,
            'selected_disk_gib' => 40,
            'period' => '14_days',
            'duration_hours' => 336,
            'provider_cost' => 1_000_000,
            'markup_percent' => 60,
            'final_amount' => 1_600_000,
            'currency' => 'IRR',
            'status' => OrderStatus::Provisioning,
            'cloud_provider' => CloudProviderType::ParsPack,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => now(),
        ]);

        $providerServerName = app(
            BuildCloudServerDataFromOrderAction::class,
        )->serverName($order);

        $cloudServer = new CloudServerData(
            id: '123456',
            name: $providerServerName,
            regionId: 'frankfurt',
            status: CloudServerStatus::Active,
            username: 'root',
            sizeId: 'deVPS2',
            imageId: 'ubuntu24-cloudinit-qcow2',
            createdAt: new DateTimeImmutable(),
            addresses: [
                new CloudServerAddressData(
                    address: '203.0.113.50',
                    version: CloudIpVersion::IPv4,
                    isPublic: true,
                    isVpc: false,
                ),
            ],
        );

        $provider = Mockery::mock(CloudProviderInterface::class);

        $inventory = Mockery::mock(CloudServerInventoryInterface::class);
        $inventory
            ->shouldReceive('listServers')
            ->once()
            ->with('frankfurt')
            ->andReturn([$cloudServer]);

        $provisioner = Mockery::mock(CloudServerProvisionerInterface::class);
        $provisioner
            ->shouldReceive('findServer')
            ->once()
            ->with('frankfurt', '123456')
            ->andReturn($cloudServer);

        $bootstrap = Mockery::mock(
            CloudServerBootstrapCredentialSourceInterface::class,
        );
        $bootstrap
            ->shouldReceive('bootstrapCredential')
            ->once()
            ->withArgs(
                static function (
                    CreateCloudServerData $request,
                    CreatedCloudServerData $server,
                ): bool {
                    return $request->regionId === 'frankfurt'
                        && $request->sizeId === 'deVPS2'
                        && $server->id === '123456';
                },
            )
            ->andReturn(
                new CloudServerBootstrapCredentialData(
                    authenticationType: AuthenticationType::SSHKey,
                    credential: 'bootstrap-private-key',
                ),
            );

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $provider,
                capabilities: [
                    CloudServerInventoryInterface::class => $inventory,
                    CloudServerProvisionerInterface::class => $provisioner,
                    CloudServerBootstrapCredentialSourceInterface::class => $bootstrap,
                ],
                registeredProviders: [CloudProviderType::ParsPack],
                purchasableProviders: [CloudProviderType::ParsPack],
            ),
        );

        $server = app(ProvisionPaidOrderAction::class)
            ->execute($order->getKey());

        $this->assertSame(OrderStatus::Fulfilled, $order->fresh()->status);
        $this->assertSame($server->getKey(), $order->fresh()->server_id);
        $this->assertSame(CloudProviderType::ParsPack, $server->cloud_provider);
        $this->assertSame(AuthenticationType::SSHKey, $server->authentication_type);
        $this->assertSame('bootstrap-private-key', $server->credential);
        $this->assertSame('203.0.113.50', $server->host);
        $this->assertSame('123456', $server->cloud_server_id);
    }
}
