<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Servers\DeleteCloudServerAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudVolumeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ArvanCloudServerVolumeCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_arvan_delete_snapshots_attached_volumes_then_deletes_server_and_volumes(): void
    {
        [$user, $server] = $this->server();
        $lifecycle = new ArvanCleanupLifecycleFake();
        $volumes = new ArvanCleanupVolumeManagerFake([
            $this->volume('volume-1'),
            $this->volume('volume-2'),
        ]);

        $this->action($lifecycle, $volumes)->handle(
            user: $user,
            serverId: (int) $server->getKey(),
        );

        $terminated = Server::withTrashed()->findOrFail($server->getKey());

        $this->assertSame(['volume-1', 'volume-2'], $terminated->termination_volume_ids);
        $this->assertNotNull($terminated->terminated_at);
        $this->assertNotNull($terminated->deleted_at);
        $this->assertSame(1, $lifecycle->deleteCalls);
        $this->assertSame(1, $volumes->listAttachedCalls);
        $this->assertSame(['volume-1', 'volume-2'], $volumes->deletedVolumeIds);
    }

    public function test_retry_reuses_persisted_volume_ids_after_partial_provider_failure(): void
    {
        [$user, $server] = $this->server();
        $lifecycle = new ArvanCleanupLifecycleFake();
        $volumes = new ArvanCleanupVolumeManagerFake([
            $this->volume('volume-1'),
        ]);
        $volumes->failNextDelete = true;
        $action = $this->action($lifecycle, $volumes);

        try {
            $action->handle(
                user: $user,
                serverId: (int) $server->getKey(),
            );
            $this->fail('Expected the first volume delete to fail.');
        } catch (CloudConnectionException) {
            // Expected; the local Server must remain for the queue retry.
        }

        $fresh = $server->fresh();
        $this->assertNotNull($fresh);
        $this->assertNull($fresh->deleted_at);
        $this->assertNull($fresh->terminated_at);
        $this->assertSame(['volume-1'], $fresh->termination_volume_ids);
        $this->assertSame(1, $volumes->listAttachedCalls);

        $lifecycle->missingAfterFirstDelete = true;
        $volumes->discoveredVolumes = [];

        $action->handle(
            user: $user,
            serverId: (int) $server->getKey(),
        );

        $terminated = Server::withTrashed()->findOrFail($server->getKey());
        $this->assertNotNull($terminated->deleted_at);
        $this->assertSame(1, $volumes->listAttachedCalls);
        $this->assertSame(['volume-1'], $volumes->deletedVolumeIds);
        $this->assertSame(2, $lifecycle->deleteCalls);
    }

    public function test_provider_not_found_for_server_and_volumes_is_idempotent_success(): void
    {
        [$user, $server] = $this->server();
        $server->forceFill([
            'termination_volume_ids' => ['volume-1'],
        ])->saveOrFail();

        $lifecycle = new ArvanCleanupLifecycleFake();
        $lifecycle->alwaysMissing = true;
        $volumes = new ArvanCleanupVolumeManagerFake([]);
        $volumes->missingVolumeIds = ['volume-1'];

        $this->action($lifecycle, $volumes)->handle(
            user: $user,
            serverId: (int) $server->getKey(),
        );

        $terminated = Server::withTrashed()->findOrFail($server->getKey());
        $this->assertNotNull($terminated->deleted_at);
        $this->assertNotNull($terminated->terminated_at);
    }

    private function action(
        ArvanCleanupLifecycleFake $lifecycle,
        ArvanCleanupVolumeManagerFake $volumes,
    ): DeleteCloudServerAction {
        return new DeleteCloudServerAction(
            lifecycle: $lifecycle,
            providers: new ArvanCleanupRegistryFake(
                lifecycle: $lifecycle,
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
            'host' => '203.0.113.90',
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

    private function volume(string $id): CloudVolumeData
    {
        return new CloudVolumeData(
            id: $id,
            name: $id,
            regionId: 'eu-west1-a',
            status: 'in-use',
        );
    }
}

final readonly class ArvanCleanupRegistryFake implements CloudProviderRegistryInterface
{
    public function __construct(
        private ArvanCleanupLifecycleFake $lifecycle,
        private ArvanCleanupVolumeManagerFake $volumes,
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
                CloudVolumeManagerInterface::class,
            ],
            true,
        );
    }
}

final class ArvanCleanupLifecycleFake implements CloudServerLifecycleInterface
{
    public int $deleteCalls = 0;

    public bool $missingAfterFirstDelete = false;

    public bool $alwaysMissing = false;

    public function powerOn(string $region, string $serverId): void {}

    public function powerOff(string $region, string $serverId): void {}

    public function reboot(string $region, string $serverId): void {}

    public function deleteServer(string $region, string $serverId): void
    {
        ++$this->deleteCalls;

        if (
            $this->alwaysMissing
            || ($this->missingAfterFirstDelete && $this->deleteCalls > 1)
        ) {
            throw new CloudResourceNotFoundException('Server not found.');
        }
    }

    /** @return list<CloudServerActionData> */
    public function getAvailableActions(string $region, string $serverId): array
    {
        return [];
    }
}

final class ArvanCleanupVolumeManagerFake implements CloudVolumeManagerInterface
{
    /** @var list<CloudVolumeData> */
    public array $discoveredVolumes;

    public int $listAttachedCalls = 0;

    public bool $failNextDelete = false;

    /** @var list<string> */
    public array $missingVolumeIds = [];

    /** @var list<string> */
    public array $deletedVolumeIds = [];

    /** @param list<CloudVolumeData> $volumes */
    public function __construct(array $volumes)
    {
        $this->discoveredVolumes = $volumes;
    }

    public function listVolumes(string $region): array
    {
        return $this->discoveredVolumes;
    }

    public function listAttachedToServer(string $region, string $serverId): array
    {
        ++$this->listAttachedCalls;

        return $this->discoveredVolumes;
    }

    public function findVolume(string $region, string $volumeId): CloudVolumeData
    {
        foreach ($this->discoveredVolumes as $volume) {
            if ($volume->id === $volumeId) {
                return $volume;
            }
        }

        throw new CloudResourceNotFoundException('Volume not found.');
    }

    public function deleteVolume(string $region, string $volumeId): void
    {
        if ($this->failNextDelete) {
            $this->failNextDelete = false;

            throw new CloudConnectionException('Volume delete temporarily failed.');
        }

        if (in_array($volumeId, $this->missingVolumeIds, true)) {
            throw new CloudResourceNotFoundException('Volume not found.');
        }

        $this->deletedVolumeIds[] = $volumeId;
    }
}
