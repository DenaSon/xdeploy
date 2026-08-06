<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Cloud\DTOs;

use App\Domain\Cloud\DTOs\ResizeCloudServerData;
use PHPUnit\Framework\TestCase;

final class ResizeCloudServerDataTest extends TestCase
{
    public function test_it_preserves_complete_resize_request(): void
    {
        $data = new ResizeCloudServerData(
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            targetSizeId: 'eco-4-8-0',
            targetDiskGiB: 100,
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
            'eco-4-8-0',
            $data->targetSizeId,
        );

        $this->assertSame(
            100,
            $data->targetDiskGiB,
        );
    }
}
