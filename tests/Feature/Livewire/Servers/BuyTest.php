<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

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

    public function test_authenticated_user_can_open_cloud_purchase_page_with_a_live_quote(): void
    {
        $user = User::factory()->create();

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
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

        /*
         * Once for the catalog and once for authoritative quote calculation.
         */
        $cloud
            ->shouldReceive('listSizes')
            ->twice()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->cloudSize(),
            ]);

        $cloud
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
            CloudProviderInterface::class,
            $cloud,
        );

        /*
         * The default disk equals the selected disk in this test, so the
         * calculator must not need a disk-price lookup. Binding the contract
         * still keeps action construction fully isolated from the real provider.
         */
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
                '1_month',
            )
            ->assertSee(
                'خرید سرور جدید',
            )
            ->assertSee(
                'Ubuntu',
            )
            ->assertSee(
                '1,152,000',
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
