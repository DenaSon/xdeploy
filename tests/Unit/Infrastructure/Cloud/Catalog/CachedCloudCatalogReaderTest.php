<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Catalog;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Infrastructure\Cloud\Catalog\CachedCloudCatalogReader;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

final class CachedCloudCatalogReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set(
            'cloud.default',
            'arvan',
        );

        config()->set(
            'cloud.catalog_cache.enabled',
            true,
        );

        config()->set(
            'cloud.catalog_cache.regions.fresh_seconds',
            600,
        );

        config()->set(
            'cloud.catalog_cache.regions.stale_seconds',
            1_200,
        );

        config()->set(
            'cloud.catalog_cache.sizes.fresh_seconds',
            600,
        );

        config()->set(
            'cloud.catalog_cache.sizes.stale_seconds',
            1_200,
        );

        config()->set(
            'cloud.catalog_cache.images.fresh_seconds',
            600,
        );

        config()->set(
            'cloud.catalog_cache.images.stale_seconds',
            1_200,
        );
    }

    protected function tearDown(): void
    {
        Cache::flush();

        parent::tearDown();
    }

    public function test_it_caches_regions_between_reads(): void
    {
        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                $this->region(
                    'ir-thr-ba1',
                ),
            ]);

        $reader = new CachedCloudCatalogReader(
            cloud: $cloud,
        );

        $first = $reader->listRegions();
        $second = $reader->listRegions();

        $this->assertSame(
            'ir-thr-ba1',
            $first[0]->id,
        );

        $this->assertSame(
            $first,
            $second,
        );
    }

    public function test_it_scopes_size_and_image_cache_by_region(): void
    {
        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->cloudSize(
                    region: 'ir-thr-ba1',
                    id: 'eco-a',
                ),
            ]);

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                $this->cloudSize(
                    region: 'eu-west1-a',
                    id: 'eco-b',
                ),
            ]);

        $cloud
            ->shouldReceive('listImages')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->image(
                    region: 'ir-thr-ba1',
                    id: 'ubuntu-a',
                ),
            ]);

        $reader = new CachedCloudCatalogReader(
            cloud: $cloud,
        );

        $this->assertSame(
            'eco-a',
            $reader->listSizes(
                'ir-thr-ba1',
            )[0]->id,
        );

        $this->assertSame(
            'eco-a',
            $reader->listSizes(
                'ir-thr-ba1',
            )[0]->id,
        );

        $this->assertSame(
            'eco-b',
            $reader->listSizes(
                'eu-west1-a',
            )[0]->id,
        );

        $this->assertSame(
            'ubuntu-a',
            $reader->listImages(
                'ir-thr-ba1',
            )[0]->id,
        );

        $this->assertSame(
            'ubuntu-a',
            $reader->listImages(
                'ir-thr-ba1',
            )[0]->id,
        );
    }

    public function test_refresh_regions_forces_a_new_provider_read(): void
    {
        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listRegions')
            ->twice()
            ->andReturn(
                [
                    $this->region(
                        'ir-thr-ba1',
                    ),
                ],
                [
                    $this->region(
                        'ir-thr-si1',
                    ),
                ],
            );

        $reader = new CachedCloudCatalogReader(
            cloud: $cloud,
        );

        $this->assertSame(
            'ir-thr-ba1',
            $reader->listRegions()[0]->id,
        );

        $this->assertSame(
            'ir-thr-si1',
            $reader->refreshRegions()[0]->id,
        );
    }

    public function test_it_can_be_disabled_without_changing_callers(): void
    {
        config()->set(
            'cloud.catalog_cache.enabled',
            false,
        );

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listRegions')
            ->twice()
            ->andReturn([
                $this->region(
                    'ir-thr-ba1',
                ),
            ]);

        $reader = new CachedCloudCatalogReader(
            cloud: $cloud,
        );

        $reader->listRegions();
        $reader->listRegions();
    }

    private function region(
        string $id,
    ): CloudRegionData {
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

    private function cloudSize(
        string $region,
        string $id,
    ): CloudSizeData {
        return new CloudSizeData(
            id: $id,
            name: $id,
            regionId: $region,
            vCpu: 1,
            memoryMiB: 1024,
            diskGiB: 25,
            category: 'eco',
            hourlyPrice: new CloudPriceData(
                amount: '1000',
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: '720000',
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }

    private function image(
        string $region,
        string $id,
    ): CloudImageData {
        return new CloudImageData(
            id: $id,
            name: 'Ubuntu 24.04',
            regionId: $region,
            distribution: 'Ubuntu',
            version: '24.04',
            architecture: null,
            minDiskGiB: 25,
            minMemoryMiB: 1024,
            supportsSshKey: true,
            supportsPassword: true,
        );
    }
}
