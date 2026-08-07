<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudServerSnapshotSummaryData
{
    public function __construct(
        public string $serverId,
        public string $serverName,
        public int $snapshotsCount,
        public ?string $status,
        public int $progress,
        public ?string $inProgressSnapshotId,
        public ?string $inProgressSnapshotName,
    ) {}

    public function hasSnapshots(): bool
    {
        return $this->snapshotsCount > 0;
    }

    public function hasSnapshotInProgress(): bool
    {
        return is_string($this->inProgressSnapshotId)
            && $this->inProgressSnapshotId !== '';
    }
}
