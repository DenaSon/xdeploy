<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerSnapshots\Actions;

use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\DTOs\CloudServerSnapshotSummaryData;

final readonly class ListCloudServerSnapshotsAction
{
    public function __construct(
        private CloudServerSnapshotManagerInterface $snapshots,
    ) {}

    /**
     * @return list<CloudServerSnapshotSummaryData>
     */
    public function execute(
        string $regionId,
    ): array {
        return $this->snapshots->listSnapshots(
            $regionId,
        );
    }
}
