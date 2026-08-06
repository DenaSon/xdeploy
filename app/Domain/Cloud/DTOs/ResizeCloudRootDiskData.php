<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class ResizeCloudRootDiskData
{
    public function __construct(
        public string $regionId,
        public string $serverId,
        public int $targetDiskGiB,
    ) {}
}
