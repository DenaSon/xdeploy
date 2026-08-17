<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\Enums\CloudProviderType;

interface CloudProviderRegistryInterface
{
    /**
     * Providers that remain operationally available for existing resources.
     *
     * @return list<CloudProviderType>
     */
    public function registeredProviders(): array;

    /**
     * Operational providers that are allowed to accept new purchases.
     *
     * @return list<CloudProviderType>
     */
    public function purchasableProviders(): array;

    public function resolve(
        CloudProviderType $provider,
    ): CloudProviderInterface;

    /**
     * @template TCapability of object
     *
     * @param  class-string<TCapability>  $capability
     * @return TCapability
     */
    public function resolveCapability(
        CloudProviderType $provider,
        string $capability,
    ): object;

    /**
     * @param  class-string  $capability
     */
    public function supportsCapability(
        CloudProviderType $provider,
        string $capability,
    ): bool;
}
