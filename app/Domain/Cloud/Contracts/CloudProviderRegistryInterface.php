<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\Enums\CloudProviderType;

interface CloudProviderRegistryInterface
{
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
}
