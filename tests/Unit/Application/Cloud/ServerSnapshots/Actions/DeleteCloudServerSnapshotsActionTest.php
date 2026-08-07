<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerSnapshots\Actions;

use App\Application\Cloud\ServerSnapshots\Actions\DeleteCloudServerSnapshotsAction;
use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\DTOs\DeleteCloudServerSnapshotsData;
use App\Domain\Cloud\DTOs\DeletedCloudServerSnapshotsData;
use Tests\TestCase;

final class DeleteCloudServerSnapshotsActionTest extends TestCase
{
    public function test_it_deletes_server_snapshots_through_the_manager(): void
    {
        $request = new DeleteCloudServerSnapshotsData(
            regionId: 'eu-west1-a',
            snapshotIds: [
                'snapshot-123',
                'snapshot-456',
            ],
        );

        $expected = new DeletedCloudServerSnapshotsData(
            message: 'snapshot deleted',
            snapshotNames: [
                'before-upgrade',
                'before-migration',
            ],
        );

        $manager = $this->createMock(
            CloudServerSnapshotManagerInterface::class,
        );

        $manager->expects($this->once())
            ->method('deleteSnapshots')
            ->with(
                $request,
            )
            ->willReturn(
                $expected,
            );

        $result = (new DeleteCloudServerSnapshotsAction(
            snapshots: $manager,
        ))->execute(
            $request,
        );

        $this->assertSame(
            $expected,
            $result,
        );
    }
}
