<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\CalculateCloudDiskPriceAction;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use Tests\TestCase;

final class CalculateCloudDiskPriceActionTest extends TestCase
{
    public function test_it_returns_calculated_disk_price_from_catalog(): void
    {
        $diskPrice = new CloudDiskPriceData(
            diskGiB: 150,

            hourlyPrice: new CloudPriceData(
                amount: '2550.25',
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Hourly,
            ),

            monthlyPrice: new CloudPriceData(
                amount: '1836180.50',
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );

        $catalog = $this->createMock(
            CloudServerResizeCatalogInterface::class,
        );

        $catalog
            ->expects($this->once())
            ->method('calculateDiskPrice')
            ->with(
                'eu-west1-a',
                'eco-4-8-0',
                150,
            )
            ->willReturn(
                $diskPrice,
            );

        $action = new CalculateCloudDiskPriceAction(
            catalog: $catalog,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            sizeId: 'eco-4-8-0',
            diskGiB: 150,
        );

        $this->assertSame(
            $diskPrice,
            $result,
        );

        $this->assertSame(
            150,
            $result->diskGiB,
        );

        $this->assertSame(
            '2550.25',
            $result->hourlyPrice->amount,
        );

        $this->assertSame(
            '1836180.50',
            $result->monthlyPrice->amount,
        );
    }
}
