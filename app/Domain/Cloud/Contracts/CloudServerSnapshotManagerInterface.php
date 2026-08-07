<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudServerSnapshotSummaryData;
use App\Domain\Cloud\DTOs\CreateCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\CreatedCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\DeleteCloudServerSnapshotsData;
use App\Domain\Cloud\DTOs\DeletedCloudServerSnapshotsData;

interface CloudServerSnapshotManagerInterface
{
    public function createSnapshot(
        CreateCloudServerSnapshotData $data,
    ): CreatedCloudServerSnapshotData;

    /**
     * @return list<CloudServerSnapshotSummaryData>
     */
    public function listSnapshots(
        string $regionId,
    ): array;

    public function deleteSnapshots(
        DeleteCloudServerSnapshotsData $data,
    ): DeletedCloudServerSnapshotsData;
}
