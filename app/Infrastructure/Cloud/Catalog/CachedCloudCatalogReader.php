<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Catalog;

use App\Domain\Cloud\Contracts\CloudCatalogReaderInterface;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\Enums\CloudProviderType;
use Closure;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

final readonly class CachedCloudCatalogReader implements CloudCatalogReaderInterface
{
    private const string CACHE_NAMESPACE =
        'xdeploy:cloud:catalog:v1';

    public function __construct(
        private CloudProviderInterface $cloud,
        private ?CloudProviderType $provider = null,
    ) {}

    public function listRegions(): array
    {
        return $this->remember(
            resource: 'regions',
            key: 'regions',
            resolver: fn (): array => $this->cloud
                ->listRegions(),
        );
    }

    public function listSizes(
        string $region,
    ): array {
        $region = $this->normalizeRegion(
            $region,
        );

        return $this->remember(
            resource: 'sizes',
            key: "region:{$region}:sizes",
            resolver: fn (): array => $this->cloud
                ->listSizes(
                    $region,
                ),
        );
    }

    public function listImages(
        string $region,
    ): array {
        $region = $this->normalizeRegion(
            $region,
        );

        return $this->remember(
            resource: 'images',
            key: "region:{$region}:images",
            resolver: fn (): array => $this->cloud
                ->listImages(
                    $region,
                ),
        );
    }

    public function warmRegion(
        string $region,
    ): void {
        $this->listSizes(
            $region,
        );

        $this->listImages(
            $region,
        );
    }

    /**
     * @return list<CloudRegionData>
     */
    public function refreshRegions(): array
    {
        Cache::forget(
            $this->cacheKey(
                'regions',
            ),
        );

        return $this->listRegions();
    }

    public function refreshRegion(
        string $region,
    ): void {
        $region = $this->normalizeRegion(
            $region,
        );

        Cache::forget(
            $this->cacheKey(
                "region:{$region}:sizes",
            ),
        );

        Cache::forget(
            $this->cacheKey(
                "region:{$region}:images",
            ),
        );

        $this->warmRegion(
            $region,
        );
    }

    private function remember(
        string $resource,
        string $key,
        Closure $resolver,
    ): array {
        if (! $this->cacheEnabled()) {
            /** @var array $value */
            $value = $resolver();

            return array_values(
                $value,
            );
        }

        [
            $freshSeconds,
            $staleSeconds,
        ] = $this->ttl(
            $resource,
        );

        /** @var array $value */
        $value = Cache::flexible(
            $this->cacheKey(
                $key,
            ),
            [
                $freshSeconds,
                $staleSeconds,
            ],
            $resolver,
            [
                'seconds' => $this->lockSeconds(),
            ],
        );

        return array_values(
            $value,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function ttl(
        string $resource,
    ): array {
        $defaults = match ($resource) {
            'sizes' => [
                600,
                3_600,
            ],

            'regions',
            'images' => [
                1_800,
                21_600,
            ],

            default => [
                300,
                1_800,
            ],
        };

        $freshSeconds = max(
            1,
            (int) config(
                "cloud.catalog_cache.{$resource}.fresh_seconds",
                $defaults[0],
            ),
        );

        $staleSeconds = max(
            $freshSeconds + 1,
            (int) config(
                "cloud.catalog_cache.{$resource}.stale_seconds",
                $defaults[1],
            ),
        );

        return [
            $freshSeconds,
            $staleSeconds,
        ];
    }

    private function lockSeconds(): int
    {
        return max(
            1,
            (int) config(
                'cloud.catalog_cache.lock_seconds',
                30,
            ),
        );
    }

    private function cacheEnabled(): bool
    {
        return (bool) config(
            'cloud.catalog_cache.enabled',
            true,
        );
    }

    private function cacheKey(
        string $suffix,
    ): string {
        $provider = $this->provider?->value
            ?? strtolower(
                trim(
                    (string) config(
                        'cloud.default',
                        'default',
                    ),
                ),
            );

        if ($provider === '') {
            $provider = 'default';
        }

        return sprintf(
            '%s:%s:%s',
            self::CACHE_NAMESPACE,
            $provider,
            $suffix,
        );
    }

    private function normalizeRegion(
        string $region,
    ): string {
        $region = strtolower(
            trim(
                $region,
            ),
        );

        if ($region === '') {
            throw new InvalidArgumentException(
                'Cloud region cannot be empty.',
            );
        }

        return $region;
    }
}
