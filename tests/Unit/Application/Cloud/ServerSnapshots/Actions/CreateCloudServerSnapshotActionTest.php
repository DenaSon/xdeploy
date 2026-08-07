<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\ServerSnapshots\Actions;

use App\Application\Cloud\ServerSnapshots\Actions\CreateCloudServerSnapshotAction;
use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\DTOs\CreateCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\CreatedCloudServerSnapshotData;
use Tests\TestCase;

final class CreateCloudServerSnapshotActionTest extends TestCase
{
    public function test_it_creates_a_server_snapshot_through_the_manager(): void
    {
        $request = new CreateCloudServerSnapshotData(
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            name: 'before-upgrade',
            description: 'Created before application upgrade.',
        );

        $expected = new CreatedCloudServerSnapshotData(
            regionId: 'eu-west1-a',
            serverId: 'server-123',
            snapshotId: 'snapshot-123',
            name: 'before-upgrade',
            message: 'snapshot created',
        );

        $manager = $this->createMock(
            CloudServerSnapshotManagerInterface::class,
        );

        $manager->expects($this->once())
            ->method('createSnapshot')
            ->with(
                $request,
            )
            ->willReturn(
                $expected,
            );

        $result = (new CreateCloudServerSnapshotAction(
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
