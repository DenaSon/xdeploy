<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateOrderAction;
use App\Application\Cloud\Actions\FilterSupportedCloudImagesAction;
use App\Application\Cloud\Actions\ListSupportedCloudImagesAction;
use App\Application\Cloud\Actions\ResolveCloudImageForOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Services\CloudPricingCalculator;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CreateOrderActionTest extends TestCase
{
    use RefreshDatabase;

    private const string REGION = 'eu-west1-a';

    private const string SIZE_ID = 'eco-2-2-0';

    private const string SUPPORTED_IMAGE_ID =
        'ubuntu-24-04-image';

    private const string PERIOD = '2_days';

    private const array SUPPORTED_OS_MATRIX = [
        'ubuntu' => [
            '24.04',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureMoney();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_order_with_verified_operating_system_snapshot(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-08 20:00:00',
            ),
        );

        $user = User::factory()->create();
        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        /*
         * ResolveCloudImageForOrderAction resolves the selected
         * size once for image compatibility validation.
         *
         * CalculateCloudPurchasePriceAction resolves the same
         * size again for authoritative pricing.
         */
        $cloud
            ->shouldReceive('listSizes')
            ->twice()
            ->with(self::REGION)
            ->andReturn([
                $this->ecoPlan(),
            ]);

        $cloud
            ->shouldReceive('listImages')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->ubuntu2404(),
                $this->ubuntu2204(),
                $this->debian12(),
            ]);

        /*
         * The pricing action first resolves the default disk
         * cost and then the selected disk cost.
         */
        $diskPricing
            ->shouldReceive('calculateDiskPrice')
            ->once()
            ->with(
                self::REGION,
                self::SIZE_ID,
                30,
            )
            ->andReturn(
                $this->diskPrice(
                    diskGiB: 30,
                    hourly: '7500',
                    monthly: '5400000',
                ),
            );

        $diskPricing
            ->shouldReceive('calculateDiskPrice')
            ->once()
            ->with(
                self::REGION,
                self::SIZE_ID,
                50,
            )
            ->andReturn(
                $this->diskPrice(
                    diskGiB: 50,
                    hourly: '12500',
                    monthly: '9000000',
                ),
            );

        $order = $this->action(
            cloud: $cloud,
            diskPricing: $diskPricing,
        )->execute(
            user: $user,
            region: self::REGION,
            sizeId: self::SIZE_ID,
            imageId: self::SUPPORTED_IMAGE_ID,
            selectedDiskGiB: 50,
            period: self::PERIOD,
        );

        $this->assertSame(
            $user->id,
            $order->user_id,
        );

        $this->assertSame(
            self::REGION,
            $order->region_id,
        );

        $this->assertSame(
            self::SIZE_ID,
            $order->size_id,
        );

        $this->assertSame(
            self::SUPPORTED_IMAGE_ID,
            $order->image_id,
        );

        $this->assertSame(
            'Ubuntu 24.04',
            $order->image_name,
        );

        $this->assertSame(
            'Ubuntu',
            $order->image_distribution,
        );

        $this->assertSame(
            '24.04',
            $order->image_version,
        );

        $this->assertSame(
            30,
            $order->default_disk_gib,
        );

        $this->assertSame(
            50,
            $order->selected_disk_gib,
        );

        $this->assertSame(
            self::PERIOD,
            $order->period,
        );

        $this->assertSame(
            48,
            $order->duration_hours,
        );

        $this->assertSame(
            1_353_600,
            $order->provider_cost,
        );

        $this->assertSame(
            60,
            $order->markup_percent,
        );

        $this->assertSame(
            2_165_760,
            $order->final_amount,
        );

        $this->assertSame(
            'IRR',
            $order->currency,
        );

        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->status,
        );

        $this->assertNull(
            $order->paid_at,
        );

        $this->assertSame(
            '2026-08-08 20:15:00',
            $order->quote_expires_at?->format(
                'Y-m-d H:i:s',
            ),
        );

        $this->assertDatabaseHas(
            'orders',
            [
                'id' => $order->id,
                'user_id' => $user->id,

                'region_id' => self::REGION,
                'size_id' => self::SIZE_ID,

                'image_id' => self::SUPPORTED_IMAGE_ID,
                'image_name' => 'Ubuntu 24.04',
                'image_distribution' => 'Ubuntu',
                'image_version' => '24.04',

                'default_disk_gib' => 30,
                'selected_disk_gib' => 50,

                'period' => self::PERIOD,
                'duration_hours' => 48,

                'provider_cost' => 1_353_600,
                'markup_percent' => 60,
                'final_amount' => 2_165_760,

                'currency' => 'IRR',
                'status' => 'pending_payment',
            ],
        );
    }

    public function test_it_rejects_image_version_not_supported_by_xdeploy(): void
    {
        $user = User::factory()->create();
        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->ecoPlan(),
            ]);

        $cloud
            ->shouldReceive('listImages')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->ubuntu2204(),
            ]);

        $diskPricing->shouldNotReceive(
            'calculateDiskPrice',
        );

        $this->expectException(
            InvalidArgumentException::class,
        );

        try {
            $this->action(
                cloud: $cloud,
                diskPricing: $diskPricing,
            )->execute(
                user: $user,
                region: self::REGION,
                sizeId: self::SIZE_ID,
                imageId: 'ubuntu-22-04-image',
                selectedDiskGiB: 30,
                period: self::PERIOD,
            );
        } finally {
            $this->assertDatabaseCount(
                'orders',
                0,
            );
        }
    }

    public function test_it_rejects_distribution_not_supported_by_xdeploy(): void
    {
        $user = User::factory()->create();
        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->ecoPlan(),
            ]);

        $cloud
            ->shouldReceive('listImages')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->debian12(),
            ]);

        $diskPricing->shouldNotReceive(
            'calculateDiskPrice',
        );

        $this->expectException(
            InvalidArgumentException::class,
        );

        try {
            $this->action(
                cloud: $cloud,
                diskPricing: $diskPricing,
            )->execute(
                user: $user,
                region: self::REGION,
                sizeId: self::SIZE_ID,
                imageId: 'debian-12-image',
                selectedDiskGiB: 30,
                period: self::PERIOD,
            );
        } finally {
            $this->assertDatabaseCount(
                'orders',
                0,
            );
        }
    }

    public function test_it_rejects_disk_smaller_than_size_default_before_image_lookup(): void
    {
        $user = User::factory()->create();
        $cloud = $this->cloud();
        $diskPricing = $this->diskPricing();

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with(self::REGION)
            ->andReturn([
                $this->ecoPlan(),
            ]);

        $cloud->shouldNotReceive(
            'listImages',
        );

        $diskPricing->shouldNotReceive(
            'calculateDiskPrice',
        );

        $this->expectException(
            InvalidArgumentException::class,
        );

        try {
            $this->action(
                cloud: $cloud,
                diskPricing: $diskPricing,
            )->execute(
                user: $user,
                region: self::REGION,
                sizeId: self::SIZE_ID,
                imageId: self::SUPPORTED_IMAGE_ID,
                selectedDiskGiB: 20,
                period: self::PERIOD,
            );
        } finally {
            $this->assertDatabaseCount(
                'orders',
                0,
            );
        }
    }

    /**
     * @return CloudProviderInterface&MockInterface
     */
    private function cloud(): CloudProviderInterface
    {
        return Mockery::mock(
            CloudProviderInterface::class,
        );
    }

    /**
     * @return CloudServerResizeCatalogInterface&MockInterface
     */
    private function diskPricing(): CloudServerResizeCatalogInterface
    {
        return Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );
    }

    private function action(
        CloudProviderInterface $cloud,
        CloudServerResizeCatalogInterface $diskPricing,
    ): CreateOrderAction {
        $filter =
            new FilterSupportedCloudImagesAction(
                operatingSystems: new SupportedOperatingSystemPolicy(
                    matrix: self::SUPPORTED_OS_MATRIX,
                ),
            );

        $supportedImages =
            new ListSupportedCloudImagesAction(
                cloud: $cloud,
                filter: $filter,
            );

        $resolveImage =
            new ResolveCloudImageForOrderAction(
                cloud: $cloud,
                supportedImages: $supportedImages,
            );

        $calculatePrice =
            new CalculateCloudPurchasePriceAction(
                cloud: $cloud,
                pricing: $diskPricing,
                calculator: new CloudPricingCalculator,
            );

        return new CreateOrderAction(
            calculatePrice: $calculatePrice,
            resolveImage: $resolveImage,
        );
    }

    private function configureMoney(): void
    {
        config()->set(
            'money.currency',
            'IRR',
        );

        config()->set(
            'money.markup_percent',
            60,
        );

        config()->set(
            'money.quote_ttl_minutes',
            15,
        );

        config()->set(
            'money.periods',
            [
                '2_days' => [
                    'label' => '۲ روزه',
                    'hours' => 48,
                    'pricing' => 'hourly',
                ],

                '14_days' => [
                    'label' => '۱۴ روزه',
                    'hours' => 336,
                    'pricing' => 'hourly',
                ],

                '1_month' => [
                    'label' => '۱ ماهه',
                    'hours' => 720,
                    'pricing' => 'monthly',
                ],
            ],
        );
    }

    private function ecoPlan(): CloudSizeData
    {
        return new CloudSizeData(
            id: self::SIZE_ID,
            name: 'eco-small4',
            regionId: self::REGION,
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 30,
            category: 'economic',

            hourlyPrice: new CloudPriceData(
                amount: '23200',
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Hourly,
            ),

            monthlyPrice: new CloudPriceData(
                amount: '16704000',
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }

    private function ubuntu2404(): CloudImageData
    {
        return $this->image(
            id: self::SUPPORTED_IMAGE_ID,
            name: 'Ubuntu 24.04',
            distribution: 'Ubuntu',
            version: '24.04',
        );
    }

    private function ubuntu2204(): CloudImageData
    {
        return $this->image(
            id: 'ubuntu-22-04-image',
            name: 'Ubuntu 22.04',
            distribution: 'Ubuntu',
            version: '22.04',
        );
    }

    private function debian12(): CloudImageData
    {
        return $this->image(
            id: 'debian-12-image',
            name: 'Debian 12',
            distribution: 'Debian',
            version: '12',
        );
    }

    private function image(
        string $id,
        string $name,
        string $distribution,
        string $version,
    ): CloudImageData {
        return new CloudImageData(
            id: $id,
            name: $name,
            regionId: self::REGION,
            distribution: $distribution,
            version: $version,
            architecture: null,
            minDiskGiB: null,
            minMemoryMiB: null,
            supportsSshKey: true,
            supportsPassword: true,
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
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Hourly,
            ),

            monthlyPrice: new CloudPriceData(
                amount: $monthly,
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }
}
