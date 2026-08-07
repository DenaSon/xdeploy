<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerSnapshots\Actions;

use App\Application\Cloud\ServerSnapshots\Actions\ListCloudServerSnapshotsAction;
use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\DTOs\CloudServerSnapshotSummaryData;
use Tests\TestCase;

final class ListCloudServerSnapshotsActionTest extends TestCase
{
    public function test_it_returns_snapshot_summaries_from_the_manager(): void
    {
        $expected = [
            new CloudServerSnapshotSummaryData(
                serverId: 'server-123',
                serverName: 'xdeploy-server',
                snapshotsCount: 2,
                status: null,
                progress: 0,
                inProgressSnapshotId: null,
                inProgressSnapshotName: null,
            ),
        ];

        $manager = $this->createMock(
            CloudServerSnapshotManagerInterface::class,
        );

        $manager->expects($this->once())
            ->method('listSnapshots')
            ->with(
                'eu-west1-a',
            )
            ->willReturn(
                $expected,
            );

        $result = (new ListCloudServerSnapshotsAction(
            snapshots: $manager,
        ))->execute(
            regionId: 'eu-west1-a',
        );

        $this->assertSame(
            $expected,
            $result,
        );
    }
}
