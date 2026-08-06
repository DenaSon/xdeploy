<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud\DTOs;

use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use PHPUnit\Framework\TestCase;

final class CloudDiskPriceDataTest extends TestCase
{
    public function test_it_preserves_disk_size_and_prices(): void
    {
        $hourlyPrice = new CloudPriceData(
            amount: '2500',
            currencyCode: null,
            billingPeriod: CloudBillingPeriod::Hourly,
        );

        $monthlyPrice = new CloudPriceData(
            amount: '1800000',
            currencyCode: null,
            billingPeriod: CloudBillingPeriod::Monthly,
        );

        $data = new CloudDiskPriceData(
            diskGiB: 75,
            hourlyPrice: $hourlyPrice,
            monthlyPrice: $monthlyPrice,
        );

        $this->assertSame(
            75,
            $data->diskGiB,
        );

        $this->assertSame(
            $hourlyPrice,
            $data->hourlyPrice,
        );

        $this->assertSame(
            $monthlyPrice,
            $data->monthlyPrice,
        );

        $this->assertSame(
            '2500',
            $data->hourlyPrice->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Hourly,
            $data->hourlyPrice->billingPeriod,
        );

        $this->assertSame(
            '1800000',
            $data->monthlyPrice->amount,
        );

        $this->assertSame(
            CloudBillingPeriod::Monthly,
            $data->monthlyPrice->billingPeriod,
        );
    }
}
