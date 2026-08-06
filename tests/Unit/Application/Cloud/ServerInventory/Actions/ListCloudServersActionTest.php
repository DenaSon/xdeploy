<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerInventory\Actions;

use App\Application\Cloud\ServerInventory\Actions\ListCloudServersAction;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\Enums\CloudServerStatus;
use Tests\TestCase;

final class ListCloudServersActionTest extends TestCase
{
    public function test_it_returns_cloud_servers_from_the_inventory(): void
    {
        $expected = [
            new CloudServerData(
                id: '93b31e1a-aa0b-4594-bf46-bcfef3ca8184',
                name: 'xdeploy-e2e-server',
                regionId: 'eu-west1-a',
                status: CloudServerStatus::Active,
                username: 'ubuntu',
                sizeId: 'eco-1-1-0',
                imageId: '3236878e-2bdc-4cdd-b082-61b3eeb3f9df',
                createdAt: null,
            ),
        ];

        $inventory = $this->createMock(
            CloudServerInventoryInterface::class,
        );

        $inventory->expects($this->once())
            ->method('listServers')
            ->with('eu-west1-a')
            ->willReturn($expected);

        $result = (new ListCloudServersAction(
            inventory: $inventory,
        ))->execute(
            region: 'eu-west1-a',
        );

        $this->assertSame(
            $expected,
            $result,
        );
    }
}
