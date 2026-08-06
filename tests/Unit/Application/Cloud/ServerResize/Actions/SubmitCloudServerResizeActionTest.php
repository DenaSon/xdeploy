<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\SubmitCloudServerResizeAction;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudServerData;
use Tests\TestCase;

final class SubmitCloudServerResizeActionTest extends TestCase
{
    public function test_it_submits_server_resize_data_to_resizer(): void
    {
        $data = new ResizeCloudServerData(
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            targetSizeId: 'eco-4-8-0',
            targetDiskGiB: 150,
        );

        $resizer = $this->createMock(
            CloudServerResizerInterface::class,
        );

        $resizer
            ->expects($this->once())
            ->method('resizeServer')
            ->with(
                $this->identicalTo(
                    $data,
                ),
            );

        $action = new SubmitCloudServerResizeAction(
            resizer: $resizer,
        );

        $action->handle(
            $data,
        );

        $this->addToAssertionCount(1);
    }
}
