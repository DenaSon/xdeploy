<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Catalog;

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
    ): CachedCloudCatalogReader {
        return new CachedCloudCatalogReader(
            cloud: $this->providers->resolve($provider),
            provider: $provider,
        );
    }
}
