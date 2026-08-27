<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudVolumeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ArvanCloudServerDeleteRetryReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_skips_server_delete_when_provider_vps_is_already_gone(): void
    {
        [$user, $server] = $this->server();
        $server->forceFill([
            'termination_volume_ids' => ['volume-1'],
        ])->saveOrFail();

        $lifecycle = new ArvanRetryLifecycleFake();
        $inventory = new ArvanRetryInventoryFake([]);
        $volumes = new ArvanRetryVolumeManagerFake();

        $this->action(
            lifecycle: $lifecycle,
            inventory: $inventory,
            volumes: $volumes,
        )->handle(
            user: $user,
            serverId: (int) $server->getKey(),
        );

        $terminated = Server::withTrashed()->findOrFail($server->getKey());

        $this->assertSame(0, $lifecycle->deleteCalls);
        $this->assertSame(1, $inventory->listCalls);
        $this->assertSame(['volume-1'], $volumes->deletedVolumeIds);
        $this->assertNotNull($terminated->terminated_at);
        $this->assertNotNull($terminated->deleted_at);
    }

    public function test_retry_deletes_server_when_provider_vps_still_exists(): void
    {
        [$user, $server] = $this->server();
        $server->forceFill([
            'termination_volume_ids' => ['volume-1'],
        ])->saveOrFail();

        $lifecycle = new ArvanRetryLifecycleFake();
        $inventory = new ArvanRetryInventoryFake([
            new CloudServerData(
                id: 'cloud-server-123',
                name: 'Arvan VPS',
                regionId: 'eu-west1-a',
                status: CloudServerStatus::Active,
                username: 'ubuntu',
                sizeId: null,
                imageId: null,
                createdAt: null,
            ),
        ]);
        $volumes = new ArvanRetryVolumeManagerFake();

        $this->action(
            lifecycle: $lifecycle,
            inventory: $inventory,
            volumes: $volumes,
        )->handle(
            user: $user,
            serverId: (int) $server->getKey(),
        );

        $this->assertSame(1, $lifecycle->deleteCalls);
        $this->assertSame(1, $inventory->listCalls);
        $this->assertSame(['volume-1'], $volumes->deletedVolumeIds);
    }

    private function action(
        ArvanRetryLifecycleFake $lifecycle,
        ArvanRetryInventoryFake $inventory,
        ArvanRetryVolumeManagerFake $volumes,
    ): DeleteCloudServerAction {
        return new DeleteCloudServerAction(
            lifecycle: $lifecycle,
            providers: new ArvanRetryRegistryFake(
                lifecycle: $lifecycle,
                inventory: $inventory,
                volumes: $volumes,
            ),
        );
    }

    /** @return array{User, Server} */
    private function server(): array
    {
        $user = User::factory()->create();
        $server = $user->servers()->create([
            'name' => 'Arvan VPS',
            'host' => '203.0.113.91',
            'port' => 22,
            'username' => 'ubuntu',
            'status' => ServerStatus::Active,
            'cloud_provider' => CloudProviderType::Arvan,
            'cloud_server_id' => 'cloud-server-123',
            'cloud_region' => 'eu-west1-a',
            'provisioned_at' => now()->subDay(),
        ]);

        return [$user, $server];
    }
}

final readonly class ArvanRetryRegistryFake implements CloudProviderRegistryInterface
{
    public function __construct(
        private ArvanRetryLifecycleFake $lifecycle,
        private ArvanRetryInventoryFake $inventory,
        private ArvanRetryVolumeManagerFake $volumes,
    ) {}

    public function registeredProviders(): array
    {
        return [CloudProviderType::Arvan];
    }

    public function purchasableProviders(): array
    {
        return [CloudProviderType::Arvan];
    }

    public function resolve(CloudProviderType $provider): CloudProviderInterface
    {
        throw new LogicException('Provider resolution is not used by this test.');
    }

    public function resolveCapability(
        CloudProviderType $provider,
        string $capability,
    ): object {
        return match ($capability) {
            CloudServerLifecycleInterface::class => $this->lifecycle,
            CloudServerInventoryInterface::class => $this->inventory,
            CloudVolumeManagerInterface::class => $this->volumes,
            default => throw new LogicException('Unexpected capability: '.$capability),
        };
    }

    public function supportsCapability(
        CloudProviderType $provider,
        string $capability,
    ): bool {
        return in_array(
            $capability,
            [
                CloudServerLifecycleInterface::class,
                CloudServerInventoryInterface::class,
                CloudVolumeManagerInterface::class,
            ],
            true,
        );
    }
}

final class ArvanRetryLifecycleFake implements CloudServerLifecycleInterface
{
    public int $deleteCalls = 0;

    public function powerOn(string $region, string $serverId): void {}

    public function powerOff(string $region, string $serverId): void {}

    public function reboot(string $region, string $serverId): void {}

    public function deleteServer(string $region, string $serverId): void
    {
        ++$this->deleteCalls;
    }

    /** @return list<CloudServerActionData> */
    public function getAvailableActions(string $region, string $serverId): array
    {
        return [];
    }
}

final class ArvanRetryInventoryFake implements CloudServerInventoryInterface
{
    public int $listCalls = 0;

    /** @param list<CloudServerData> $servers */
    public function __construct(
        private readonly array $servers,
    ) {}

    public function listServers(string $region): array
    {
        ++$this->listCalls;

        return $this->servers;
    }
}

final class ArvanRetryVolumeManagerFake implements CloudVolumeManagerInterface
{
    /** @var list<string> */
    public array $deletedVolumeIds = [];

    public function listVolumes(string $region): array
    {
        return [];
    }

    public function listAttachedToServer(string $region, string $serverId): array
    {
        return [];
    }

    public function findVolume(string $region, string $volumeId): CloudVolumeData
    {
        throw new CloudResourceNotFoundException('Volume not found.');
    }

    public function deleteVolume(string $region, string $volumeId): void
    {
        $this->deletedVolumeIds[] = $volumeId;
    }
}
