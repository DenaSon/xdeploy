<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Services\CloudPricingCalculator;
use Tests\TestCase;

final class CloudPricingCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('money.currency', 'IRR');
        config()->set('money.markup_percent', 60);

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

    public function test_it_calculates_two_day_price_for_default_disk(): void
    {
        $result = $this->calculator()->calculate(
            baseHourlyPrice: '23200',
            baseMonthlyPrice: '16704000',

            defaultDiskHourlyPrice: '0',
            defaultDiskMonthlyPrice: '0',

            selectedDiskHourlyPrice: '0',
            selectedDiskMonthlyPrice: '0',

            period: '2_days',
        );

        $this->assertSame('2_days', $result['period']);
        $this->assertSame(48, $result['duration_hours']);

        $this->assertSame(
            '1113600',
            $result['provider_cost'],
        );

        $this->assertSame(
            60,
            $result['markup_percent'],
        );

        $this->assertSame(
            '1781760',
            $result['final_amount'],
        );

        $this->assertSame(
            'IRR',
            $result['currency'],
        );
    }

    public function test_it_adds_only_extra_disk_cost_to_base_plan(): void
    {
        $result = $this->calculator()->calculate(
            baseHourlyPrice: '23200',
            baseMonthlyPrice: '16704000',

            defaultDiskHourlyPrice: '7500',
            defaultDiskMonthlyPrice: '5400000',

            selectedDiskHourlyPrice: '12500',
            selectedDiskMonthlyPrice: '9000000',

            period: '2_days',
        );

        /*
         * Extra disk:
         *
         * 12,500 - 7,500 = 5,000 IRR/hour
         *
         * Effective provider price:
         *
         * 23,200 + 5,000 = 28,200 IRR/hour
         *
         * 28,200 × 48 = 1,353,600
         */
        $this->assertSame(
            '1353600',
            $result['provider_cost'],
        );

        $this->assertSame(
            '2165760',
            $result['final_amount'],
        );
    }

    public function test_it_calculates_fourteen_days_as_336_hours(): void
    {
        $result = $this->calculator()->calculate(
            baseHourlyPrice: '23200',
            baseMonthlyPrice: '16704000',

            defaultDiskHourlyPrice: '0',
            defaultDiskMonthlyPrice: '0',

            selectedDiskHourlyPrice: '0',
            selectedDiskMonthlyPrice: '0',

            period: '14_days',
        );

        $this->assertSame(
            336,
            $result['duration_hours'],
        );

        $this->assertSame(
            '7795200',
            $result['provider_cost'],
        );

        $this->assertSame(
            '12472320',
            $result['final_amount'],
        );
    }

    public function test_monthly_period_uses_provider_monthly_price(): void
    {
        $result = $this->calculator()->calculate(
            /*
             * Intentionally use values where:
             *
             * hourly × 720 != monthly price
             *
             * so the test proves that monthlyPrice is actually used.
             */
            baseHourlyPrice: '100',
            baseMonthlyPrice: '50000',

            defaultDiskHourlyPrice: '0',
            defaultDiskMonthlyPrice: '0',

            selectedDiskHourlyPrice: '0',
            selectedDiskMonthlyPrice: '0',

            period: '1_month',
        );

        $this->assertSame(
            720,
            $result['duration_hours'],
        );

        $this->assertSame(
            '50000',
            $result['provider_cost'],
        );

        $this->assertSame(
            '80000',
            $result['final_amount'],
        );
    }

    private function calculator(): CloudPricingCalculator
    {
        return new CloudPricingCalculator;
    }
}
