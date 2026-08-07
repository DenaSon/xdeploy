<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class DeleteCloudServerSnapshotsData
{
    /**
     * @param  non-empty-list<string>  $snapshotIds
     */
    public function __construct(
        public string $regionId,
        public array $snapshotIds,
    ) {}
}
