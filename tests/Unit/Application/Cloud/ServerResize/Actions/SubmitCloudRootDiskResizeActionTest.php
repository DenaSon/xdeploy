<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\ServerResize\Actions\SubmitCloudRootDiskResizeAction;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudRootDiskData;
use Tests\TestCase;

final class SubmitCloudRootDiskResizeActionTest extends TestCase
{
    public function test_it_submits_root_disk_resize_data_to_resizer(): void
    {
        $data = new ResizeCloudRootDiskData(
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            targetDiskGiB: 150,
        );

        $resizer = $this->createMock(
            CloudServerResizerInterface::class,
        );

        $resizer
            ->expects($this->once())
            ->method('resizeRootDisk')
            ->with(
                $this->identicalTo(
                    $data,
                ),
            );

        $action = new SubmitCloudRootDiskResizeAction(
            resizer: $resizer,
        );

        $action->handle(
            $data,
        );

        $this->addToAssertionCount(1);
    }
}
