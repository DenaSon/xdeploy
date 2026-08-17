<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Cloud\Contracts\CloudCatalogReaderInterface;
use App\Domain\Cloud\Contracts\CloudCatalogReaderResolverInterface;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Livewire\Servers\Buy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\Support\CloudProviderRegistryStub;
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

        $provider = Mockery::mock(
            CloudProviderInterface::class,
        );

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $provider,
            ),
        );

        $resolver = Mockery::mock(
            CloudCatalogReaderResolverInterface::class,
        );

        $resolver->shouldNotReceive('resolve');

        $this->app->instance(
            CloudCatalogReaderResolverInterface::class,
            $resolver,
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
            ->assertSet(
                'provider',
                CloudProviderType::Arvan->value,
            )
            ->assertSee(
                'خرید VPS',
            );
    }

    public function test_authenticated_user_can_load_purchase_catalog(): void
    {
        $user = User::factory()->create();

        $catalog = $this->catalogMock(
            region: $this->region(
                id: 'ir-thr-ba1',
                name: 'Iran / Tehran / Bamdad',
                country: 'Iran',
                city: 'Tehran',
                dataCenter: 'Bamdad',
            ),
            size: $this->cloudSize(),
            image: $this->image(
                id: 'ubuntu-24',
                region: 'ir-thr-ba1',
                minDiskGiB: 30,
            ),
        );

        $resolver = Mockery::mock(
            CloudCatalogReaderResolverInterface::class,
        );

        $resolver
            ->shouldReceive('resolve')
            ->twice()
            ->with(CloudProviderType::Arvan)
            ->andReturn($catalog);

        $this->app->instance(
            CloudCatalogReaderResolverInterface::class,
            $resolver,
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

        $resizeCatalog = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );

        $resizeCatalog->shouldNotReceive(
            'calculateDiskPrice',
        );

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $cloud,
                capabilities: [
                    CloudServerResizeCatalogInterface::class => $resizeCatalog,
                ],
            ),
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
                'provider',
                CloudProviderType::Arvan->value,
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

    public function test_user_can_switch_provider_and_receive_a_fresh_provider_scoped_catalog_and_quote(): void
    {
        config()->set('cloud.default', CloudProviderType::Arvan->value);

        $user = User::factory()->create();

        $arvanSize = $this->cloudSize();
        $liaraSize = $this->cloudSize(
            id: 'standard-base-g2',
            region: 'iran',
            diskGiB: 20,
            hourly: '14583',
            monthly: '10500000',
        );

        $arvanCatalog = $this->catalogMock(
            region: $this->region(
                id: 'ir-thr-ba1',
                name: 'Iran / Tehran / Bamdad',
                country: 'Iran',
                city: 'Tehran',
                dataCenter: 'Bamdad',
            ),
            size: $arvanSize,
            image: $this->image(
                id: 'ubuntu-24',
                region: 'ir-thr-ba1',
                minDiskGiB: 30,
            ),
        );

        $liaraCatalog = $this->catalogMock(
            region: $this->region(
                id: 'iran',
                name: 'Iran',
                country: 'IR',
            ),
            size: $liaraSize,
            image: $this->image(
                id: 'ubuntu-24.04',
                region: 'iran',
                minDiskGiB: null,
            ),
        );

        $resolver = Mockery::mock(
            CloudCatalogReaderResolverInterface::class,
        );

        $resolver
            ->shouldReceive('resolve')
            ->twice()
            ->with(CloudProviderType::Arvan)
            ->andReturn($arvanCatalog);

        $resolver
            ->shouldReceive('resolve')
            ->twice()
            ->with(CloudProviderType::Liara)
            ->andReturn($liaraCatalog);

        $this->app->instance(
            CloudCatalogReaderResolverInterface::class,
            $resolver,
        );

        $arvan = Mockery::mock(
            CloudProviderInterface::class,
        );
        $liara = Mockery::mock(
            CloudProviderInterface::class,
        );
        $arvanPricing = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );
        $liaraPricing = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );

        $arvan
            ->shouldReceive('listSizes')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $arvanSize,
            ]);

        $liara
            ->shouldReceive('listSizes')
            ->once()
            ->with('iran')
            ->andReturn([
                $liaraSize,
            ]);

        $arvanPricing->shouldNotReceive('calculateDiskPrice');
        $liaraPricing->shouldNotReceive('calculateDiskPrice');

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistry(
                providers: [
                    CloudProviderType::Arvan->value => $arvan,
                    CloudProviderType::Liara->value => $liara,
                ],
                purchasableProviders: [
                    CloudProviderType::Arvan->value,
                    CloudProviderType::Liara->value,
                ],
                capabilities: [
                    CloudProviderType::Arvan->value => [
                        CloudServerResizeCatalogInterface::class => $arvanPricing,
                    ],
                    CloudProviderType::Liara->value => [
                        CloudServerResizeCatalogInterface::class => $liaraPricing,
                    ],
                ],
            ),
        );

        $this->actingAs($user);

        Livewire::test(Buy::class)
            ->call('loadCatalog')
            ->assertSet('provider', CloudProviderType::Arvan->value)
            ->assertSet('regionId', 'ir-thr-ba1')
            ->assertSet('sizeId', 'eco-2-2')
            ->assertSee('ابر آروان')
            ->assertSee('لیارا')
            ->call('selectProvider', CloudProviderType::Liara->value)
            ->assertSet('catalogLoaded', true)
            ->assertSet('provider', CloudProviderType::Liara->value)
            ->assertSet('regionGroup', 'iran')
            ->assertSet('regionId', 'iran')
            ->assertSet('sizeId', 'standard-base-g2')
            ->assertSet('imageId', 'ubuntu-24.04')
            ->assertSet('selectedDiskGiB', 20)
            ->assertSet('quote.selected_disk_gib', 20)
            ->assertSee('لیارا');
    }

    /**
     * @return CloudCatalogReaderInterface&\Mockery\MockInterface
     */
    private function catalogMock(
        CloudRegionData $region,
        CloudSizeData $size,
        CloudImageData $image,
    ): CloudCatalogReaderInterface {
        $catalog = Mockery::mock(
            CloudCatalogReaderInterface::class,
        );

        $catalog
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([
                $region,
            ]);

        $catalog
            ->shouldReceive('listSizes')
            ->once()
            ->with($region->id)
            ->andReturn([
                $size,
            ]);

        $catalog
            ->shouldReceive('listImages')
            ->once()
            ->with($region->id)
            ->andReturn([
                $image,
            ]);

        return $catalog;
    }

    private function region(
        string $id,
        string $name,
        ?string $country = null,
        ?string $city = null,
        ?string $dataCenter = null,
    ): CloudRegionData {
        return new CloudRegionData(
            id: $id,
            displayName: $name,
            country: $country,
            city: $city,
            dataCenter: $dataCenter,
            canCreateServers: true,
            isVisible: true,
            supportsVolumeBacked: true,
        );
    }

    private function image(
        string $id,
        string $region,
        ?int $minDiskGiB,
    ): CloudImageData {
        return new CloudImageData(
            id: $id,
            name: 'Ubuntu 24.04',
            regionId: $region,
            distribution: 'Ubuntu',
            version: '24.04',
            architecture: null,
            minDiskGiB: $minDiskGiB,
            minMemoryMiB: 1024,
            supportsSshKey: true,
            supportsPassword: true,
        );
    }

    private function cloudSize(
        string $id = 'eco-2-2',
        string $region = 'ir-thr-ba1',
        int $diskGiB = 30,
        string $hourly = '1000',
        string $monthly = '720000',
    ): CloudSizeData {
        return new CloudSizeData(
            id: $id,
            name: $id,
            regionId: $region,
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: $diskGiB,
            category: 'cloud',
            hourlyPrice: new CloudPriceData(
                amount: $hourly,
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: $monthly,
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }
}
