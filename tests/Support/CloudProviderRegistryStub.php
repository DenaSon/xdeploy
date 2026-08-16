<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use LogicException;

final readonly class CloudProviderRegistryStub implements CloudProviderRegistryInterface
{
    /**
     * @param  array<class-string, object>  $capabilities
     * @param  list<CloudProviderType>  $registeredProviders
     * @param  list<CloudProviderType>|null  $purchasableProviders
     */
    public function __construct(
        private CloudProviderInterface $provider,
        private array $capabilities = [],
        private array $registeredProviders = [
            CloudProviderType::Arvan,
        ],
        private ?array $purchasableProviders = null,
    ) {}

    public function registeredProviders(): array
    {
        return $this->registeredProviders;
    }

    public function purchasableProviders(): array
    {
        return $this->purchasableProviders
            ?? $this->registeredProviders;
    }

    public function resolve(
        CloudProviderType $provider,
    ): CloudProviderInterface {
        return $this->provider;
    }

    public function resolveCapability(
        CloudProviderType $provider,
        string $capability,
    ): object {
        $resolved = $this->capabilities[$capability]
            ?? $this->provider;

        if (! $resolved instanceof $capability) {
            throw new LogicException(
                sprintf(
                    'Test provider does not support capability [%s].',
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
        $resolved = $this->capabilities[$capability]
            ?? $this->provider;

        return $resolved instanceof $capability;
    }
}
