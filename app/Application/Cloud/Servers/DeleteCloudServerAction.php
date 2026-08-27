<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;

final readonly class DeleteCloudServerAction
{
    public function __construct(
        private CloudServerLifecycleInterface $lifecycle,
        private ?CloudProviderRegistryInterface $providers = null,
    ) {}

    public function handle(
        User $user,
        int $serverId,
    ): void {
        $server = $this->ownedServer(
            user: $user,
            serverId: $serverId,
        );

        $cloudRegion = $this->requiredCloudMetadata(
            server: $server,
            attribute: 'cloud_region',
        );

        $cloudServerId = $this->requiredCloudMetadata(
            server: $server,
            attribute: 'cloud_server_id',
        );

        $provider = $this->requiredCloudProvider(
            $server,
        );

        $lifecycle = $this->lifecycleFor(
            $provider,
        );

        $isArvanRetry = $provider === CloudProviderType::Arvan
            && is_array($server->termination_volume_ids);

        [$volumeManager, $volumeIds] = $this->prepareArvanVolumeCleanup(
            server: $server,
            provider: $provider,
            region: $cloudRegion,
            providerServerId: $cloudServerId,
        );

        if (
            ! $isArvanRetry
            || $this->arvanServerExists(
                region: $cloudRegion,
                providerServerId: $cloudServerId,
            )
        ) {
            /*
             * Provider deletion is the authoritative external side effect.
             * Not-found is also a successful terminal state.
             */
            try {
                $lifecycle->deleteServer(
                    region: $cloudRegion,
                    serverId: $cloudServerId,
                );
            } catch (CloudResourceNotFoundException) {
                // Desired state already reached at the owning provider.
            }
        }

        if ($volumeManager instanceof CloudVolumeManagerInterface) {
            foreach ($volumeIds as $volumeId) {
                try {
                    $volumeManager->deleteVolume(
                        region: $cloudRegion,
                        volumeId: $volumeId,
                    );
                } catch (CloudResourceNotFoundException) {
                    // Desired state already reached at the owning provider.
                }
            }
        }

        /*
         * Local finalization happens only after all provider resources have
         * reached the desired deleted state. Any provider failure therefore
         * keeps the Server row available for the existing queue retry flow.
         */
        $server->forceFill([
            'status' => ServerStatus::Inactive,
            'terminated_at' => $server->terminated_at
                ?? now(),
            'termination_last_error' => null,
        ])->saveOrFail();

        $server->delete();
    }

    /**
     * Snapshot Arvan volume IDs before deleting the VPS so retries never lose
     * the cleanup targets after the provider detaches or removes the server.
     *
     * @return array{CloudVolumeManagerInterface|null, list<string>}
     */
    private function prepareArvanVolumeCleanup(
        Server $server,
        CloudProviderType $provider,
        string $region,
        string $providerServerId,
    ): array {
        if (
            $provider !== CloudProviderType::Arvan
            || ! $this->providers instanceof CloudProviderRegistryInterface
        ) {
            return [null, []];
        }

        /** @var CloudVolumeManagerInterface $volumeManager */
        $volumeManager = $this->providers->resolveCapability(
            provider: CloudProviderType::Arvan,
            capability: CloudVolumeManagerInterface::class,
        );

        $persistedIds = $server->termination_volume_ids;

        if (is_array($persistedIds)) {
            return [
                $volumeManager,
                $this->requiredVolumeIds($persistedIds),
            ];
        }

        $volumeIds = [];

        foreach (
            $volumeManager->listAttachedToServer(
                region: $region,
                serverId: $providerServerId,
            ) as $volume
        ) {
            $volumeIds[] = $volume->id;
        }

        $volumeIds = $this->requiredVolumeIds(
            $volumeIds,
        );

        /*
         * Persist before the destructive server DELETE. If deleting a Volume
         * fails later, TerminateExpiredCloudServerJob retries using this exact
         * snapshot instead of trying to rediscover detached resources.
         */
        $server->forceFill([
            'termination_volume_ids' => $volumeIds,
        ])->saveOrFail();

        return [$volumeManager, $volumeIds];
    }

    private function arvanServerExists(
        string $region,
        string $providerServerId,
    ): bool {
        if (! $this->providers instanceof CloudProviderRegistryInterface) {
            return true;
        }

        if (! $this->providers->supportsCapability(
            provider: CloudProviderType::Arvan,
            capability: CloudServerInventoryInterface::class,
        )) {
            return true;
        }

        /** @var CloudServerInventoryInterface $inventory */
        $inventory = $this->providers->resolveCapability(
            provider: CloudProviderType::Arvan,
            capability: CloudServerInventoryInterface::class,
        );

        foreach ($inventory->listServers($region) as $providerServer) {
            if (trim($providerServer->id) === $providerServerId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<string>
     */
    private function requiredVolumeIds(
        array $values,
    ): array {
        $ids = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value !== '') {
                $ids[$value] = $value;
            }
        }

        if ($ids === []) {
            throw new CloudValidationException(
                'ArvanCloud volume cleanup targets could not be determined.',
            );
        }

        return array_values($ids);
    }

    private function lifecycleFor(
        CloudProviderType $provider,
    ): CloudServerLifecycleInterface {
        if ($this->providers instanceof CloudProviderRegistryInterface) {
            /** @var CloudServerLifecycleInterface $lifecycle */
            $lifecycle = $this->providers->resolveCapability(
                provider: $provider,
                capability: CloudServerLifecycleInterface::class,
            );

            return $lifecycle;
        }

        if ($provider !== CloudProviderType::Arvan) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud provider [%s] cannot be resolved without the provider registry.',
                    $provider->value,
                ),
            );
        }

        return $this->lifecycle;
    }

    private function requiredCloudProvider(
        Server $server,
    ): CloudProviderType {
        $provider = $server->cloud_provider;

        if (! $provider instanceof CloudProviderType) {
            throw new CloudValidationException(
                'Cloud server metadata is incomplete.',
            );
        }

        return $provider;
    }

    private function ownedServer(
        User $user,
        int $serverId,
    ): Server {
        return $user
            ->servers()
            ->whereKey($serverId)
            ->firstOrFail();
    }

    private function requiredCloudMetadata(
        Server $server,
        string $attribute,
    ): string {
        $value = $server->getAttribute(
            $attribute,
        );

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw new CloudValidationException(
                'Cloud server metadata is incomplete.',
            );
        }

        return trim($value);
    }
}
