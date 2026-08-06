<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\CalculateCloudSizeAction;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;
use Tests\TestCase;

final class CalculateCloudSizeActionTest extends TestCase
{
    public function test_it_returns_calculated_size_from_catalog(): void
    {
        $calculatedSize = new CloudSizeData(
            id: 'eco-4-8-0',
            name: 'eco-large',
            regionId: 'eu-west1-a',
            vCpu: 4,
            memoryMiB: 8192,
            diskGiB: 150,
            category: 'economic',
            hourlyPrice: null,
            monthlyPrice: null,
        );

        $catalog = $this->createMock(
            CloudServerResizeCatalogInterface::class,
        );

        $catalog
            ->expects($this->once())
            ->method('calculateSize')
            ->with(
                'eu-west1-a',
                'eco-4-8-0',
                150,
            )
            ->willReturn(
                $calculatedSize,
            );

        $action = new CalculateCloudSizeAction(
            catalog: $catalog,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            sizeId: 'eco-4-8-0',
            diskGiB: 150,
        );

        $this->assertSame(
            $calculatedSize,
            $result,
        );
    }
}
