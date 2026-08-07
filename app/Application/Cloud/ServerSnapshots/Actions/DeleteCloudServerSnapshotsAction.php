<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerSnapshots\Actions;

use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\DTOs\DeleteCloudServerSnapshotsData;
use App\Domain\Cloud\DTOs\DeletedCloudServerSnapshotsData;

final readonly class DeleteCloudServerSnapshotsAction
{
    public function __construct(
        private CloudServerSnapshotManagerInterface $snapshots,
    ) {}

    public function execute(
        DeleteCloudServerSnapshotsData $data,
    ): DeletedCloudServerSnapshotsData {
        return $this->snapshots->deleteSnapshots(
            $data,
        );
    }
}
