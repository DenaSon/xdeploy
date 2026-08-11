<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Cloud\Contracts\CloudCatalogReaderInterface;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Livewire\Servers\Buy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class BuyTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_cloud_purchase_page(): void
    {
        $this->get(
            route(
                'panel.servers.buy',
            ),
        )->assertRedirect(
            route(
                'login',
            ),
        );
    }

    public function test_cloud_catalog_is_deferred_until_livewire_init_request(): void
    {
        $user = User::factory()->create();

        $catalog = Mockery::mock(
            CloudCatalogReaderInterface::class,
        );

        $catalog->shouldNotReceive(
            'listRegions',
        );

        $this->app->instance(
            CloudCatalogReaderInterface::class,
            $catalog,
        );

        $this->actingAs(
            $user,
        );

        Livewire::test(
            Buy::class,
        )
            ->assertSet(
                'catalogLoaded',
                false,
            )
            ->assertSee(
                'خرید VPS',
            );
    }

    public function test_authenticated_user_can_load_purchase_catalog(): void
    {
        $user = User::factory()->create();

        $catalog = Mockery::mock(
            CloudCatalogReaderInterface::class,
        );

        $catalog
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                new CloudRegionData(
                    id: 'ir-thr-ba1',
                    displayName: 'Iran / Tehran / Bamdad',
                    country: 'Iran',
                    city: 'Tehran',
                    dataCenter: 'Bamdad',
                    canCreateServers: true,
                    isVisible: true,
                    supportsVolumeBacked: true,
                ),
            ]);

        $catalog
            ->shouldReceive('listSizes')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->cloudSize(),
            ]);

        $catalog
            ->shouldReceive('listImages')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                new CloudImageData(
                    id: 'ubuntu-24',
                    name: 'Ubuntu 24.04',
                    regionId: 'ir-thr-ba1',
                    distribution: 'Ubuntu',
                    version: '24.04',
                    architecture: null,
                    minDiskGiB: 30,
                    minMemoryMiB: 1024,
                    supportsSshKey: true,
                    supportsPassword: true,
                ),
            ]);

        $this->app->instance(
            CloudCatalogReaderInterface::class,
            $catalog,
        );

        /*
         * Quote calculation remains authoritative and provider-backed.
         * This test only verifies that the Livewire purchase screen can
         * load its catalog and settle on a valid initial selection.
         */
        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->cloudSize(),
            ]);

        $this->app->instance(
            CloudProviderInterface::class,
            $cloud,
        );

        $resizeCatalog = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );

        $resizeCatalog->shouldNotReceive(
            'calculateDiskPrice',
        );

        $this->app->instance(
            CloudServerResizeCatalogInterface::class,
            $resizeCatalog,
        );

        $this->actingAs(
            $user,
        );

        Livewire::test(
            Buy::class,
        )
            ->call(
                'loadCatalog',
            )
            ->assertSet(
                'catalogLoaded',
                true,
            )
            ->assertSet(
                'regionGroup',
                'iran',
            )
            ->assertSet(
                'regionId',
                'ir-thr-ba1',
            )
            ->assertSet(
                'sizeId',
                'eco-2-2',
            )
            ->assertSet(
                'imageId',
                'ubuntu-24',
            )
            ->assertSet(
                'selectedDiskGiB',
                30,
            )
            ->assertSet(
                'period',
                '14_days',
            )
            ->assertSee(
                'موقعیت',
            )
            ->assertSee(
                'Ubuntu',
            )
            ->assertSee(
                'تومان',
            );
    }

    private function cloudSize(): CloudSizeData
    {
        return new CloudSizeData(
            id: 'eco-2-2',
            name: 'Eco 2C / 2GB',
            regionId: 'ir-thr-ba1',
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 30,
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
}
