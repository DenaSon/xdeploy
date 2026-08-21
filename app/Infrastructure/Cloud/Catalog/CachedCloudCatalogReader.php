<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Catalog;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudPurchaseCatalogSourceInterface;
use App\Domain\Cloud\Contracts\RefreshableCloudCatalogReaderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use Closure;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;
use UnexpectedValueException;

final readonly class CachedCloudCatalogReader implements RefreshableCloudCatalogReaderInterface
{
    private const string CACHE_NAMESPACE =
        'xdeploy:cloud:catalog:v2';

    public function __construct(
        private CloudProviderInterface $cloud,
        private ?CloudProviderType $provider = null,
    ) {}

    public function listRegions(): array
    {
        return $this->remember(
            resource: 'regions',
            key: 'regions',
            expectedClass: CloudRegionData::class,
            resolver: fn (): array => $this->providerRegions(),
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
            expectedClass: CloudSizeData::class,
            resolver: fn (): array => $this->providerSizes(
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
            expectedClass: CloudImageData::class,
            resolver: fn (): array => $this->providerImages(
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
        return $this->refresh(
            resource: 'regions',
            key: 'regions',
            expectedClass: CloudRegionData::class,
            resolver: fn (): array => $this->providerRegions(),
        );
    }

    public function refreshSizes(
        string $region,
    ): array {
        $region = $this->normalizeRegion(
            $region,
        );

        return $this->refresh(
            resource: 'sizes',
            key: "region:{$region}:sizes",
            expectedClass: CloudSizeData::class,
            resolver: fn (): array => $this->providerSizes(
                $region,
            ),
        );
    }

    public function refreshImages(
        string $region,
    ): array {
        $region = $this->normalizeRegion(
            $region,
        );

        return $this->refresh(
            resource: 'images',
            key: "region:{$region}:images",
            expectedClass: CloudImageData::class,
            resolver: fn (): array => $this->providerImages(
                $region,
            ),
        );
    }

    public function refreshRegion(
        string $region,
    ): void {
        $region = $this->normalizeRegion(
            $region,
        );

        $this->refreshSizes(
            $region,
        );

        $this->refreshImages(
            $region,
        );
    }

    /**
     * @param  class-string  $expectedClass
     */
    private function remember(
        string $resource,
        string $key,
        string $expectedClass,
        Closure $resolver,
    ): array {
        if (! $this->cacheEnabled()) {
            return $this->resolve(
                resolver: $resolver,
                expectedClass: $expectedClass,
            );
        }

        [
            $freshSeconds,
            $staleSeconds,
        ] = $this->ttl(
            $resource,
        );

        $cacheKey = $this->cacheKey(
            $key,
        );

        $resolverStarted = false;
        $resolverCompleted = false;
        $resolvedValue = [];

        $guardedResolver = function () use (
            $resolver,
            $expectedClass,
            &$resolverStarted,
            &$resolverCompleted,
            &$resolvedValue,
        ): array {
            $resolverStarted = true;
            $resolvedValue = $this->resolve(
                resolver: $resolver,
                expectedClass: $expectedClass,
            );
            $resolverCompleted = true;

            return $resolvedValue;
        };

        try {
            /** @var mixed $value */
            $value = Cache::flexible(
                $cacheKey,
                [
                    $freshSeconds,
                    $staleSeconds,
                ],
                $guardedResolver,
                [
                    'seconds' => $this->lockSeconds(),
                ],
            );

            return $this->validatedValues(
                value: $value,
                expectedClass: $expectedClass,
            );
        } catch (Throwable $exception) {
            /*
             * Provider and mapper failures remain authoritative and must be
             * handled by the caller. Cache transport, persistence, or stale
             * payload failures must not make the purchase catalog unavailable.
             */
            if (
                $resolverStarted
                && ! $resolverCompleted
            ) {
                throw $exception;
            }

            report(
                $exception,
            );

            if ($resolverCompleted) {
                return $resolvedValue;
            }

            $this->forgetBestEffort(
                $cacheKey,
            );

            return $guardedResolver();
        }
    }

    /**
     * @param  class-string  $expectedClass
     */
    private function refresh(
        string $resource,
        string $key,
        string $expectedClass,
        Closure $resolver,
    ): array {
        $value = $this->resolve(
            resolver: $resolver,
            expectedClass: $expectedClass,
        );

        if (! $this->cacheEnabled()) {
            return $value;
        }

        $cacheKey = $this->cacheKey(
            $key,
        );

        [, $staleSeconds] = $this->ttl(
            $resource,
        );

        try {
            Cache::putMany(
                [
                    $cacheKey => $value,
                    CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$cacheKey => time(),
                ],
                $staleSeconds,
            );
        } catch (Throwable $exception) {
            report(
                $exception,
            );
        }

        return $value;
    }

    /**
     * @param  class-string  $expectedClass
     */
    private function resolve(
        Closure $resolver,
        string $expectedClass,
    ): array {
        /** @var mixed $value */
        $value = $resolver();

        return $this->validatedValues(
            value: $value,
            expectedClass: $expectedClass,
        );
    }

    /**
     * @param  class-string  $expectedClass
     */
    private function validatedValues(
        mixed $value,
        string $expectedClass,
    ): array {
        if (! is_array($value)) {
            throw new UnexpectedValueException(
                'Cloud catalog cache payload must be an array.',
            );
        }

        foreach ($value as $item) {
            if (! $item instanceof $expectedClass) {
                throw new UnexpectedValueException(
                    sprintf(
                        'Cloud catalog cache payload must contain only [%s] values.',
                        $expectedClass,
                    ),
                );
            }
        }

        return array_values(
            $value,
        );
    }

    private function forgetBestEffort(
        string $cacheKey,
    ): void {
        foreach (
            [
                $cacheKey,
                CacheRepository::FLEXIBLE_CREATED_KEY_PREFIX.$cacheKey,
            ] as $key
        ) {
            try {
                Cache::forget(
                    $key,
                );
            } catch (Throwable $exception) {
                report(
                    $exception,
                );
            }
        }
    }

    private function providerRegions(): array
    {
        return $this->cloud instanceof CloudPurchaseCatalogSourceInterface
            ? $this->cloud->listPurchaseRegions()
            : $this->cloud->listRegions();
    }

    private function providerSizes(
        string $region,
    ): array {
        return $this->cloud instanceof CloudPurchaseCatalogSourceInterface
            ? $this->cloud->listPurchaseSizes(
                $region,
            )
            : $this->cloud->listSizes(
                $region,
            );
    }

    private function providerImages(
        string $region,
    ): array {
        return $this->cloud instanceof CloudPurchaseCatalogSourceInterface
            ? $this->cloud->listPurchaseImages(
                $region,
            )
            : $this->cloud->listImages(
                $region,
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
