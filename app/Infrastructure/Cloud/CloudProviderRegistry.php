<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;

final readonly class CloudProviderRegistry implements CloudProviderRegistryInterface
{
    /**
     * @var array<string, CloudProviderInterface>
     */
    private array $providers;

    /**
     * @param  array<string, CloudProviderInterface>  $providers
     */
    public function __construct(
        array $providers,
    ) {
        $normalized = [];

        foreach ($providers as $key => $provider) {
            $providerKey = strtolower(
                trim((string) $key),
            );

            if ($providerKey === '') {
                throw new CloudConfigurationException(
                    'Cloud provider registry keys cannot be empty.',
                );
            }

            $normalized[$providerKey] = $provider;
        }

        $this->providers = $normalized;
    }

    public function resolve(
        CloudProviderType $provider,
    ): CloudProviderInterface {
        $resolved = $this->providers[$provider->value]
            ?? null;

        if (! $resolved instanceof CloudProviderInterface) {
            throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] is not registered.',
                    $provider->value,
                ),
            );
        }

        return $resolved;
    }

    /**
     * @template TCapability of object
     *
     * @param  class-string<TCapability>  $capability
     * @return TCapability
     */
    public function resolveCapability(
        CloudProviderType $provider,
        string $capability,
    ): object {
        $resolved = $this->resolve(
            $provider,
        );

        if (! $resolved instanceof $capability) {
            throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] does not support capability [%s].',
                    $provider->value,
                    $capability,
                ),
            );
        }

        return $resolved;
    }
}
