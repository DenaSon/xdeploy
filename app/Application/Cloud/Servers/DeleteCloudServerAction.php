<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
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

        $server->forceFill([
            'status' => ServerStatus::Inactive,
            'terminated_at' => $server->terminated_at
                ?? now(),
            'termination_last_error' => null,
        ])->saveOrFail();

        $server->delete();
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
