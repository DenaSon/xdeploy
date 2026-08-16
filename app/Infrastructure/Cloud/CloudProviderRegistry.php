<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;

final readonly class CloudProviderRegistry implements CloudProviderRegistryInterface
{
    /** @var array<string, CloudProviderInterface> */
    private array $providers;

    /** @var array<string, array<class-string, object>> */
    private array $capabilities;

    /**
     * @param  array<string, CloudProviderInterface>  $providers
     * @param  array<string, array<class-string, object>>  $capabilities
     */
    public function __construct(
        array $providers,
        array $capabilities = [],
    ) {
        $normalizedProviders = [];

        foreach ($providers as $key => $provider) {
            $normalizedProviders[$this->normalizeProviderKey($key)] = $provider;
        }

        $normalizedCapabilities = [];

        foreach ($capabilities as $key => $providerCapabilities) {
            $providerKey = $this->normalizeProviderKey($key);

            foreach ($providerCapabilities as $contract => $implementation) {
                if (
                    ! is_string($contract)
                    || trim($contract) === ''
                    || ! $implementation instanceof $contract
                ) {
                    throw new CloudConfigurationException(
                        sprintf(
                            'Cloud provider capability override [%s] is invalid.',
                            is_string($contract) ? $contract : 'unknown',
                        ),
                    );
                }

                $normalizedCapabilities[$providerKey][$contract] = $implementation;
            }
        }

        $this->providers = $normalizedProviders;
        $this->capabilities = $normalizedCapabilities;
    }

    public function registeredProviders(): array
    {
        $providers = [];

        foreach (array_keys($this->providers) as $providerKey) {
            $provider = CloudProviderType::tryFrom($providerKey);

            if (! $provider instanceof CloudProviderType) {
                throw new CloudConfigurationException(
                    sprintf(
                        'The registered cloud provider [%s] has no supported provider type.',
                        $providerKey,
                    ),
                );
            }

            $providers[] = $provider;
        }

        return $providers;
    }

    public function resolve(
        CloudProviderType $provider,
    ): CloudProviderInterface {
        $resolved = $this->providers[$provider->value] ?? null;

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
     * @param  class-string<TCapability>  $capability
     * @return TCapability
     */
    public function resolveCapability(
        CloudProviderType $provider,
        string $capability,
    ): object {
        $override = $this->capabilities[$provider->value][$capability] ?? null;

        if ($override instanceof $capability) {
            return $override;
        }

        $resolved = $this->resolve($provider);

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

    public function supportsCapability(
        CloudProviderType $provider,
        string $capability,
    ): bool {
        $resolved = $this->resolve($provider);
        $override = $this->capabilities[$provider->value][$capability] ?? null;

        return $override instanceof $capability
            || $resolved instanceof $capability;
    }

    private function normalizeProviderKey(mixed $key): string
    {
        $providerKey = strtolower(trim((string) $key));

        if ($providerKey === '') {
            throw new CloudConfigurationException(
                'Cloud provider registry keys cannot be empty.',
            );
        }

        return $providerKey;
    }
}
