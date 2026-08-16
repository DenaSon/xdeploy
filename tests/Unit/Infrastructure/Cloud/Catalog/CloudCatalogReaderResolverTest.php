<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Catalog;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\Catalog\CloudCatalogReaderResolver;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class CloudCatalogReaderResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('cloud.catalog_cache.enabled', true);
        config()->set('cloud.catalog_cache.regions.fresh_seconds', 600);
        config()->set('cloud.catalog_cache.regions.stale_seconds', 1200);
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_it_keeps_provider_catalog_caches_isolated(): void
    {
        $arvan = Mockery::mock(CloudProviderInterface::class);
        $liara = Mockery::mock(CloudProviderInterface::class);

        $arvan
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                $this->region('ir-thr-ba1'),
            ]);

        $liara
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                $this->region('iran'),
            ]);

        $resolver = new CloudCatalogReaderResolver(
            providers: new CloudProviderRegistry(
                providers: [
                    CloudProviderType::Arvan->value => $arvan,
                    CloudProviderType::Liara->value => $liara,
                ],
            ),
        );

        $arvanCatalog = $resolver->resolve(
            CloudProviderType::Arvan,
        );
        $liaraCatalog = $resolver->resolve(
            CloudProviderType::Liara,
        );

        $this->assertSame(
            'ir-thr-ba1',
            $arvanCatalog->listRegions()[0]->id,
        );
        $this->assertSame(
            'iran',
            $liaraCatalog->listRegions()[0]->id,
        );

        /*
         * Second reads must hit two different cache keys rather than leaking
         * one provider's catalog into the other.
         */
        $this->assertSame(
            'ir-thr-ba1',
            $arvanCatalog->listRegions()[0]->id,
        );
        $this->assertSame(
            'iran',
            $liaraCatalog->listRegions()[0]->id,
        );
    }

    private function region(string $id): CloudRegionData
    {
        return new CloudRegionData(
            id: $id,
            displayName: $id,
            country: null,
            city: null,
            dataCenter: null,
            canCreateServers: true,
            isVisible: true,
            supportsVolumeBacked: true,
        );
    }
}
