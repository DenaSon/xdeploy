<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\DTOs\CreateCloudServerSnapshotData;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudSnapshotResponseMapper;
use PHPUnit\Framework\TestCase;

final class ArvanCloudSnapshotResponseMapperTest extends TestCase
{
    public function test_it_maps_a_created_snapshot(): void
    {
        $result = $this->mapper()->mapCreatedSnapshot(
            payload: [
                'instance_id' => 'server-123',
                'snapshot_id' => 'snapshot-123',
                'message' => 'snapshot created from server instance',
            ],
            request: new CreateCloudServerSnapshotData(
                regionId: 'eu-west1-a',
                serverId: 'server-123',
                name: 'snapshot-one',
            ),
        );

        $this->assertSame(
            'eu-west1-a',
            $result->regionId,
        );

        $this->assertSame(
            'server-123',
            $result->serverId,
        );

        $this->assertSame(
            'snapshot-123',
            $result->snapshotId,
        );

        $this->assertSame(
            'snapshot-one',
            $result->name,
        );
    }

    public function test_it_maps_snapshot_summaries(): void
    {
        $result = $this->mapper()->mapSnapshotSummaries([
            'data' => [
                [
                    'instance_id' => 'server-123',
                    'instance_name' => 'xdeploy-server',
                    'snapshots_count' => 2,
                    'status' => '',
                    'progress' => 0,
                    'in_progress_snapshot_id' => '',
                    'in_progress_snapshot_name' => '',
                ],
            ],
        ]);

        $this->assertCount(
            1,
            $result,
        );

        $this->assertSame(
            2,
            $result[0]->snapshotsCount,
        );

        $this->assertNull(
            $result[0]->status,
        );

        $this->assertNull(
            $result[0]->inProgressSnapshotId,
        );
    }

    public function test_it_maps_null_data_to_an_empty_snapshot_list(): void
    {
        $result = $this->mapper()->mapSnapshotSummaries([
            'data' => null,
        ]);

        $this->assertSame(
            [],
            $result,
        );
    }

    public function test_it_maps_the_live_snapshot_deletion_shape(): void
    {
        $result = $this->mapper()->mapDeletedSnapshots([
            'code' => 0,
            'message' => 'snapshot deleted',
            'errors' => [
                [
                    'snapshot-one',
                ],
            ],
        ]);

        $this->assertSame(
            'snapshot deleted',
            $result->message,
        );

        $this->assertSame(
            [
                'snapshot-one',
            ],
            $result->snapshotNames,
        );

        $this->assertSame(
            1,
            $result->deletedCount(),
        );
    }

    public function test_it_rejects_a_nonzero_deletion_code(): void
    {
        $this->expectException(
            CloudUnexpectedResponseException::class,
        );

        $this->mapper()->mapDeletedSnapshots([
            'code' => 1,
            'message' => 'snapshot deletion failed',
            'errors' => [],
        ]);
    }

    private function mapper(): ArvanCloudSnapshotResponseMapper
    {
        return new ArvanCloudSnapshotResponseMapper;
    }
}
