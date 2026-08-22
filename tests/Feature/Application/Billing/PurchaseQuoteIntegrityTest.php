<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateOrderAction;
use App\Application\Cloud\Actions\FilterSupportedCloudImagesAction;
use App\Application\Cloud\Actions\ResolveCloudImageForOrderAction;
use App\Domain\Billing\DTOs\PurchaseQuoteExpectationData;
use App\Domain\Billing\Exceptions\PurchaseQuoteChangedException;
use App\Domain\Billing\Services\CloudPricingCalculator;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudPurchaseCatalogSourceInterface;
use App\Domain\Cloud\Contracts\CloudPurchasePricingSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use App\Infrastructure\Cloud\CloudProviderRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PurchaseQuoteIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private const string REGION = 'ir-thr-ba1';
    private const string SIZE = 'eco-2-2';
    private const string IMAGE = 'ubuntu-24';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('money.currency', 'IRR');
        config()->set('money.markup_percent', 0);
        config()->set('money.quote_ttl_minutes', 15);
        config()->set('money.periods', [
            '2_days' => [
                'label' => '۲ روزه',
                'hours' => 48,
                'pricing' => 'hourly',
            ],
        ]);
    }

    public function test_changed_authoritative_quote_creates_no_order(): void
    {
        $user = User::factory()->create();
        $action = $this->action();

        try {
            $action->execute(
                user: $user,
                region: self::REGION,
                sizeId: self::SIZE,
                imageId: self::IMAGE,
                selectedDiskGiB: 40,
                period: '2_days',
                provider: CloudProviderType::Arvan,
                expectedQuote: new PurchaseQuoteExpectationData(
                    finalAmount: 50_000,
                    currency: 'IRR',
                    durationHours: 48,
                    selectedDiskGiB: 40,
                ),
            );

            $this->fail('Expected the changed purchase quote to be rejected.');
        } catch (PurchaseQuoteChangedException $exception) {
            $this->assertSame(
                '50400',
                $exception->currentQuote->finalAmount,
            );
            $this->assertSame(
                40,
                $exception->currentQuote->selectedDiskGiB,
            );
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_matching_quote_creates_order_using_bounded_purchase_reads(): void
    {
        $user = User::factory()->create();
        $action = $this->action();

        $order = $action->execute(
            user: $user,
            region: self::REGION,
            sizeId: self::SIZE,
            imageId: self::IMAGE,
            selectedDiskGiB: 40,
            period: '2_days',
            provider: CloudProviderType::Arvan,
            expectedQuote: new PurchaseQuoteExpectationData(
                finalAmount: 50_400,
                currency: 'IRR',
                durationHours: 48,
                selectedDiskGiB: 40,
            ),
        );

        $this->assertSame(50_400, $order->final_amount);
        $this->assertSame('IRR', $order->currency);
        $this->assertSame(CloudProviderType::Arvan, $order->cloud_provider);
    }

    private function action(): CreateOrderAction
    {
        $provider = Mockery::mock(
            sprintf(
                '%s, %s, %s',
                CloudProviderInterface::class,
                CloudPurchaseCatalogSourceInterface::class,
                CloudPurchasePricingSourceInterface::class,
            ),
        );

        $provider->shouldNotReceive('listSizes');
        $provider->shouldNotReceive('listImages');

        $provider
            ->shouldReceive('listPurchaseSizes')
            ->twice()
            ->with(self::REGION)
            ->andReturn([$this->cloudSize()]);

        $provider
            ->shouldReceive('listPurchaseImages')
            ->once()
            ->with(self::REGION)
            ->andReturn([$this->image()]);

        $provider
            ->shouldReceive('calculatePurchaseDiskPrice')
            ->once()
            ->with(self::REGION, self::SIZE, 30)
            ->andReturn(
                $this->diskPrice(
                    diskGiB: 30,
                    hourly: '100',
                ),
            );

        $provider
            ->shouldReceive('calculatePurchaseDiskPrice')
            ->once()
            ->with(self::REGION, self::SIZE, 40)
            ->andReturn(
                $this->diskPrice(
                    diskGiB: 40,
                    hourly: '150',
                ),
            );

        $resizePricing = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );
        $resizePricing->shouldNotReceive('calculateDiskPrice');

        $registry = new CloudProviderRegistry(
            providers: [
                CloudProviderType::Arvan->value => $provider,
            ],
            purchasableProviders: [
                CloudProviderType::Arvan->value,
            ],
            capabilities: [
                CloudProviderType::Arvan->value => [
                    CloudServerResizeCatalogInterface::class => $resizePricing,
                ],
            ],
        );

        $filter = new FilterSupportedCloudImagesAction(
            operatingSystems: new SupportedOperatingSystemPolicy(
                matrix: [
                    'ubuntu' => ['24.04'],
                ],
            ),
        );

        return new CreateOrderAction(
            calculatePrice: new CalculateCloudPurchasePriceAction(
                calculator: new CloudPricingCalculator,
                providers: $registry,
            ),
            resolveImage: new ResolveCloudImageForOrderAction(
                providers: $registry,
                filter: $filter,
            ),
            providers: $registry,
        );
    }

    private function cloudSize(): CloudSizeData
    {
        return new CloudSizeData(
            id: self::SIZE,
            name: 'eco-2-2',
            regionId: self::REGION,
            vCpu: 2,
            memoryMiB: 2048,
            diskGiB: 30,
            category: 'economic',
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

    private function image(): CloudImageData
    {
        return new CloudImageData(
            id: self::IMAGE,
            name: 'Ubuntu 24.04',
            regionId: self::REGION,
            distribution: 'Ubuntu',
            version: '24.04',
            architecture: null,
            minDiskGiB: 30,
            minMemoryMiB: 1024,
            supportsSshKey: true,
            supportsPassword: true,
        );
    }

    private function diskPrice(
        int $diskGiB,
        string $hourly,
    ): CloudDiskPriceData {
        return new CloudDiskPriceData(
            diskGiB: $diskGiB,
            hourlyPrice: new CloudPriceData(
                amount: $hourly,
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: (string) ((int) $hourly * 720),
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }
}
