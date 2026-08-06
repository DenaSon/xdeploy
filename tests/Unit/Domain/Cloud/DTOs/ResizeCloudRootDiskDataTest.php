<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud\DTOs;

use App\Domain\Cloud\DTOs\ResizeCloudRootDiskData;
use PHPUnit\Framework\TestCase;

final class ResizeCloudRootDiskDataTest extends TestCase
{
    public function test_it_preserves_root_disk_resize_request(): void
    {
        $data = new ResizeCloudRootDiskData(
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            targetDiskGiB: 150,
        );

        $this->assertSame(
            'eu-west1-a',
            $data->regionId,
        );

        $this->assertSame(
            'server-123',
            $data->serverId,
        );

        $this->assertSame(
            150,
            $data->targetDiskGiB,
        );
    }
}
