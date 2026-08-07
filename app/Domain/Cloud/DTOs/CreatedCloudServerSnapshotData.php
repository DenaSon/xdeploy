<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CreatedCloudServerSnapshotData
{
    public function __construct(
        public string $regionId,
        public string $serverId,
        public string $snapshotId,
        public string $name,
        public string $message,
    ) {}
}
