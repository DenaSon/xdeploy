<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CalculateCloudPurchasePriceAction;
use App\Application\Billing\Actions\CreateOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Services\CloudPricingCalculator;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

final class CreateOrderActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('money.currency', 'IRR');
        config()->set('money.markup_percent', 60);
        config()->set('money.quote_ttl_minutes', 15);

        config()->set('money.periods', [
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
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_pending_order_with_price_snapshot(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-08 20:00:00'),
        );

        $user = User::factory()->create();

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $diskPricing = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                $this->ecoSmall4(),
            ]);

        $diskPricing
            ->shouldReceive('calculateDiskPrice')
            ->once()
            ->with(
                'eu-west1-a',
                'eco-2-2-0',
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
                'eu-west1-a',
                'eco-2-2-0',
                50,
            )
            ->andReturn(
                $this->diskPrice(
                    diskGiB: 50,
                    hourly: '12500',
                    monthly: '9000000',
                ),
            );

        $calculatePrice = new CalculateCloudPurchasePriceAction(
            cloud: $cloud,
            pricing: $diskPricing,
            calculator: new CloudPricingCalculator,
        );

        $action = new CreateOrderAction(
            calculatePrice: $calculatePrice,
        );

        $order = $action->execute(
            user: $user,
            region: 'eu-west1-a',
            sizeId: 'eco-2-2-0',
            selectedDiskGiB: 50,
            period: '2_days',
        );

        $this->assertSame(
            $user->id,
            $order->user_id,
        );

        $this->assertSame(
            'eu-west1-a',
            $order->region_id,
        );

        $this->assertSame(
            'eco-2-2-0',
            $order->size_id,
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
            '2_days',
            $order->period,
        );

        $this->assertSame(
            48,
            $order->duration_hours,
        );

        $this->assertSame(
            1353600,
            $order->provider_cost,
        );

        $this->assertSame(
            60,
            $order->markup_percent,
        );

        $this->assertSame(
            2165760,
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

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => $user->id,

            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',

            'default_disk_gib' => 30,
            'selected_disk_gib' => 50,

            'period' => '2_days',
            'duration_hours' => 48,

            'provider_cost' => 1353600,
            'markup_percent' => 60,
            'final_amount' => 2165760,

            'currency' => 'IRR',
            'status' => 'pending_payment',
        ]);
    }

    public function test_it_rejects_disk_smaller_than_size_default(): void
    {
        $user = User::factory()->create();

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $diskPricing = Mockery::mock(
            CloudServerResizeCatalogInterface::class,
        );

        $cloud
            ->shouldReceive('listSizes')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                $this->ecoSmall4(),
            ]);

        /*
         * Validation must fail before asking Arvan for disk pricing.
         */
        $diskPricing
            ->shouldNotReceive('calculateDiskPrice');

        $calculatePrice = new CalculateCloudPurchasePriceAction(
            cloud: $cloud,
            pricing: $diskPricing,
            calculator: new CloudPricingCalculator,
        );

        $action = new CreateOrderAction(
            calculatePrice: $calculatePrice,
        );

        $this->expectException(
            InvalidArgumentException::class,
        );

        try {
            $action->execute(
                user: $user,
                region: 'eu-west1-a',
                sizeId: 'eco-2-2-0',
                selectedDiskGiB: 20,
                period: '2_days',
            );
        } finally {
            $this->assertDatabaseCount(
                'orders',
                0,
            );
        }
    }

    private function ecoSmall4(): CloudSizeData
    {
        return new CloudSizeData(
            id: 'eco-2-2-0',
            name: 'eco-small4',
            regionId: 'eu-west1-a',

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
