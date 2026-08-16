<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Application\Cloud\DTOs\CloudServerTargetData;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Models\Server;

final readonly class CloudServerCapabilityResolver
{
    public function __construct(
        private CloudProviderRegistryInterface $providers,
    ) {}

    public function target(Server $server): CloudServerTargetData
    {
        $provider = $server->cloud_provider;

        if (! $provider instanceof CloudProviderType) {
            throw new CloudValidationException(
                'Cloud server provider metadata is incomplete.',
            );
        }

        return new CloudServerTargetData(
            provider: $provider,
            region: $this->requiredMetadata(
                server: $server,
                attribute: 'cloud_region',
            ),
            serverId: $this->requiredMetadata(
                server: $server,
                attribute: 'cloud_server_id',
            ),
        );
    }

    /**
     * @template TCapability of object
     *
     * @param  class-string<TCapability>  $capability
     * @return array{0: CloudServerTargetData, 1: TCapability}
     */
    public function resolve(
        Server $server,
        string $capability,
    ): array {
        $target = $this->target(
            $server,
        );

        /** @var TCapability $resolved */
        $resolved = $this->providers->resolveCapability(
            provider: $target->provider,
            capability: $capability,
        );

        return [
            $target,
            $resolved,
        ];
    }

    /**
     * @param  class-string  $capability
     */
    public function supports(
        Server $server,
        string $capability,
    ): bool {
        try {
            $target = $this->target(
                $server,
            );
        } catch (CloudValidationException) {
            return false;
        }

        return $this->providers->supportsCapability(
            provider: $target->provider,
            capability: $capability,
        );
    }

    private function requiredMetadata(
        Server $server,
        string $attribute,
    ): string {
        $value = $server->getAttribute(
            $attribute,
        );

        if (! is_string($value) || trim($value) === '') {
            throw new CloudValidationException(
                'Cloud server metadata is incomplete.',
            );
        }

        return trim($value);
    }
}
