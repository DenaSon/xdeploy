<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Cloud\Contracts\CloudCatalogReaderInterface;
use App\Domain\Cloud\Contracts\CloudCatalogReaderResolverInterface;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudPurchasePricingSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\Contracts\RefreshableCloudCatalogReaderInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Livewire\Servers\Buy;
use App\Models\User;
use App\Support\Cloud\CloudProviderPublicIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
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
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Arvan,
                ),
            )
            ->assertSee(
                'خرید VPS',
            );
    }

    public function test_authenticated_user_can_load_purchase_catalog(): void
    {
        $user = User::factory()->create();
        $size = $this->cloudSize(
            diskGiB: 25,
        );

        $catalog = $this->catalogMock(
            region: $this->region(
                id: 'ir-thr-ba1',
                name: 'Iran / Tehran / Bamdad',
                country: 'Iran',
                city: 'Tehran',
                dataCenter: 'Bamdad',
            ),
            size: $size,
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
         * The preview reuses the loaded size snapshot while custom-disk
         * pricing still uses the bounded purchase transport. Order creation
         * performs the authoritative provider-backed calculation separately.
         */
        $cloud = Mockery::mock(
            sprintf(
                '%s, %s',
                CloudProviderInterface::class,
                CloudPurchasePricingSourceInterface::class,
            ),
        );
        $cloud->shouldNotReceive('listSizes');
        $cloud
            ->shouldReceive('calculatePurchaseDiskPrice')
            ->once()
            ->with(
                'ir-thr-ba1',
                $size->id,
                25,
            )
            ->andReturn(
                $this->diskPrice(
                    diskGiB: 25,
                    hourly: '100',
                    monthly: '72000',
                ),
            );
        $cloud
            ->shouldReceive('calculatePurchaseDiskPrice')
            ->once()
            ->with(
                'ir-thr-ba1',
                $size->id,
                30,
            )
            ->andReturn(
                $this->diskPrice(
                    diskGiB: 30,
                    hourly: '120',
                    monthly: '86400',
                ),
            );

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
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Arvan,
                ),
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

        $arvan->shouldNotReceive('listSizes');
        $liara->shouldNotReceive('listSizes');

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
            ->assertSet(
                'provider',
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Arvan,
                ),
            )
            ->assertSet('regionId', 'ir-thr-ba1')
            ->assertSet('sizeId', 'eco-2-2')
            ->assertSee('Core-1')
            ->assertSee('Core-2')
            ->assertDontSee('ابر آروان')
            ->assertDontSee('لیارا')
            ->call(
                'selectProvider',
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Liara,
                ),
            )
            ->assertSet('catalogLoaded', true)
            ->assertSet(
                'provider',
                CloudProviderPublicIdentity::code(
                    CloudProviderType::Liara,
                ),
            )
            ->assertSet('regionGroup', 'iran')
            ->assertSet('regionId', 'iran')
            ->assertSet('sizeId', 'standard-base-g2')
            ->assertSet('imageId', 'ubuntu-24.04')
            ->assertSet('selectedDiskGiB', 20)
            ->assertSet('quote.selected_disk_gib', 20)
            ->assertSee('Core-2')
            ->assertDontSee('لیارا');
    }

    public function test_retry_forces_a_fresh_provider_scoped_catalog_read(): void
    {
        $user = User::factory()->create();
        $region = $this->region(
            id: 'ir-thr-ba1',
            name: 'Iran / Tehran / Bamdad',
            country: 'Iran',
            city: 'Tehran',
            dataCenter: 'Bamdad',
        );
        $size = $this->cloudSize();
        $image = $this->image(
            id: 'ubuntu-24',
            region: $region->id,
            minDiskGiB: 30,
        );

        $catalog = Mockery::mock(
            RefreshableCloudCatalogReaderInterface::class,
        );

        $catalog
            ->shouldReceive('listRegions')
            ->once()
            ->andReturn([$region]);
        $catalog
            ->shouldReceive('listSizes')
            ->once()
            ->with($region->id)
            ->andReturn([$size]);
        $catalog
            ->shouldReceive('listImages')
            ->once()
            ->with($region->id)
            ->andReturn([$image]);
        $catalog
            ->shouldReceive('refreshRegions')
            ->once()
            ->andReturn([$region]);
        $catalog
            ->shouldReceive('refreshSizes')
            ->once()
            ->with($region->id)
            ->andReturn([$size]);
        $catalog
            ->shouldReceive('refreshImages')
            ->once()
            ->with($region->id)
            ->andReturn([$image]);

        $resolver = Mockery::mock(
            CloudCatalogReaderResolverInterface::class,
        );
        $resolver
            ->shouldReceive('resolve')
            ->times(4)
            ->with(CloudProviderType::Arvan)
            ->andReturn($catalog);

        $this->app->instance(
            CloudCatalogReaderResolverInterface::class,
            $resolver,
        );

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );
        $cloud->shouldNotReceive('listSizes');

        $pricing = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );
        $pricing->shouldNotReceive('calculateDiskPrice');

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            new CloudProviderRegistryStub(
                provider: $cloud,
                capabilities: [
                    CloudServerResizeCatalogInterface::class => $pricing,
                ],
            ),
        );

        $this->actingAs($user);

        Livewire::test(Buy::class)
            ->call('loadCatalog')
            ->assertSet('catalogLoaded', true)
            ->call('reloadCatalog')
            ->assertSet('catalogLoaded', true)
            ->assertSet('catalogError', null)
            ->assertSet('regionId', $region->id)
            ->assertSet('sizeId', $size->id)
            ->assertSet('imageId', $image->id);
    }

    /**
     * @return CloudCatalogReaderInterface&MockInterface
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

    private function diskPrice(
        int $diskGiB,
        string $hourly,
        string $monthly,
    ): CloudDiskPriceData {
        return new CloudDiskPriceData(
            diskGiB: $diskGiB,
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
