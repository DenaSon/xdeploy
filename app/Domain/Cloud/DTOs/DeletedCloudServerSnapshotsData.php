<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class DeletedCloudServerSnapshotsData
{
    /**
     * @param  list<string>  $snapshotNames
     */
    public function __construct(
        public string $message,
        public array $snapshotNames,
    ) {}

    public function deletedCount(): int
    {
        return count(
            $this->snapshotNames,
        );
    }
}
