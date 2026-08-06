<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\ListAvailableServerResizePlansAction;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;
use Tests\TestCase;

final class ListAvailableServerResizePlansActionTest extends TestCase
{
    public function test_it_returns_available_resize_plans_from_catalog(): void
    {
        $size = $this->cloudSize();

        $catalog = $this->createMock(
            CloudServerResizeCatalogInterface::class,
        );

        $catalog
            ->expects($this->once())
            ->method('listServerResizePlans')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn([
                $size,
            ]);

        $action = new ListAvailableServerResizePlansAction(
            catalog: $catalog,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            [
                $size,
            ],
            $result,
        );
    }

    public function test_it_preserves_an_empty_resize_plan_list(): void
    {
        $catalog = $this->createMock(
            CloudServerResizeCatalogInterface::class,
        );

        $catalog
            ->expects($this->once())
            ->method('listServerResizePlans')
            ->with(
                'eu-west1-a',
                'server-123',
            )
            ->willReturn([]);

        $action = new ListAvailableServerResizePlansAction(
            catalog: $catalog,
        );

        $result = $action->handle(
            region: 'eu-west1-a',
            serverId: 'server-123',
        );

        $this->assertSame(
            [],
            $result,
        );
    }

    private function cloudSize(): CloudSizeData
    {
        return new CloudSizeData(
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
    }
}
