<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudPurchaseCatalogSourceInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class CheckCloudProviderHealthCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::flush();
    }

    public function test_probe_command_uses_registered_provider_catalog_capability(): void
    {
        $catalog = $this->catalog();
        $registry = Mockery::mock(CloudProviderRegistryInterface::class);
        $registry->shouldReceive('registeredProviders')
            ->once()
            ->andReturn([CloudProviderType::Arvan]);
        $registry->shouldReceive('supportsCapability')
            ->once()
            ->with(
                CloudProviderType::Arvan,
                CloudPurchaseCatalogSourceInterface::class,
            )
            ->andReturnTrue();
        $registry->shouldReceive('resolveCapability')
            ->once()
            ->with(
                CloudProviderType::Arvan,
                CloudPurchaseCatalogSourceInterface::class,
            )
            ->andReturn($catalog);

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            $registry,
        );

        $this->assertSame(
            0,
            Artisan::call('cloud:providers:health-check'),
        );
    }

    public function test_unexpected_probe_response_marks_provider_degraded(): void
    {
        $catalog = $this->catalog(
            new CloudUnexpectedResponseException(
                'Unexpected provider schema.',
                200,
            ),
        );
        $registry = Mockery::mock(CloudProviderRegistryInterface::class);
        $registry->shouldReceive('registeredProviders')
            ->once()
            ->andReturn([CloudProviderType::Liara]);
        $registry->shouldReceive('supportsCapability')
            ->once()
            ->andReturnTrue();
        $registry->shouldReceive('resolveCapability')
            ->once()
            ->andReturn($catalog);

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            $registry,
        );

        $this->assertSame(
            1,
            Artisan::call('cloud:providers:health-check'),
        );

        $snapshot = $this->app->make(
            CloudProviderHealthEngine::class,
        )->snapshot(CloudProviderType::Liara);

        $this->assertNotNull($snapshot);
        $this->assertSame(
            CloudProviderHealthStatus::Degraded,
            $snapshot->status,
        );
        $this->assertSame(
            CloudProviderHealthFailureCategory::UnexpectedResponse,
            $snapshot->lastErrorCategory,
        );
        $this->assertSame('health.probe', $snapshot->lastOperation);
    }

    private function catalog(
        ?CloudUnexpectedResponseException $failure = null,
    ): CloudPurchaseCatalogSourceInterface {
        return new class($failure) implements CloudPurchaseCatalogSourceInterface
        {
            public function __construct(
                private readonly ?CloudUnexpectedResponseException $failure,
            ) {}

            /** @return list<CloudRegionData> */
            public function listPurchaseRegions(): array
            {
                if ($this->failure instanceof CloudUnexpectedResponseException) {
                    throw $this->failure;
                }

                return [];
            }

            /** @return list<CloudSizeData> */
            public function listPurchaseSizes(string $region): array
            {
                return [];
            }

            /** @return list<CloudImageData> */
            public function listPurchaseImages(string $region): array
            {
                return [];
            }
        };
    }
}
