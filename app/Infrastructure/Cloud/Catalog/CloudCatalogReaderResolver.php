<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Catalog;

use App\Domain\Cloud\Contracts\CloudCatalogReaderInterface;
use App\Domain\Cloud\Contracts\CloudCatalogReaderResolverInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;

final readonly class CloudCatalogReaderResolver implements CloudCatalogReaderResolverInterface
{
    public function __construct(
        private CloudProviderRegistryInterface $providers,
    ) {}

    public function resolve(
        CloudProviderType $provider,
    ): CloudCatalogReaderInterface {
        return new CachedCloudCatalogReader(
            cloud: $this->providers->resolve($provider),
            provider: $provider,
        );
    }
}
