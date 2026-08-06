<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\GetCloudSizeAction;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;
use Tests\TestCase;

final class GetCloudSizeActionTest extends TestCase
{
    public function test_it_returns_size_details_from_catalog(): void
    {
        $size = new CloudSizeData(
            id: 'eco-4-8-0',
            name: 'eco-large',
            regionId: 'eu-west1-a',
            vCpu: 4,
            memoryMiB: 8192,
            diskGiB: 100,
            category: 'economic',
            hourlyPrice: null,
            monthlyPrice: null,
        );

        $catalog = $this->createMock(
            CloudServerResizeCatalogInterface::class,
        );

        $catalog
            ->expects($this->once())
            ->method('findSize')
            ->with(
                'eu-west1-a',
                'eco-4-8-0',
            )
            ->willReturn(
                $size,
            );

        $action = new GetCloudSizeAction(
            catalog: $catalog,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            sizeId: 'eco-4-8-0',
        );

        $this->assertSame(
            $size,
            $result,
        );
    }
}
