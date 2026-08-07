<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerSnapshots\Actions;

use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\DTOs\CreateCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\CreatedCloudServerSnapshotData;

final readonly class CreateCloudServerSnapshotAction
{
    public function __construct(
        private CloudServerSnapshotManagerInterface $snapshots,
    ) {}

    public function execute(
        CreateCloudServerSnapshotData $data,
    ): CreatedCloudServerSnapshotData {
        return $this->snapshots->createSnapshot(
            $data,
        );
    }
}
