<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\DTOs\CreateCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\DeleteCloudServerSnapshotsData;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudSnapshotManager;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudV2Client;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudSnapshotResponseMapper;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ArvanCloudSnapshotManagerTest extends TestCase
{
    private const string BASE_URL =
        'https://api.example.test/ecc/v2';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_creates_a_server_snapshot(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'instance_id' => 'server-123',
                'snapshot_id' => 'snapshot-123',
                'message' => 'snapshot created from xdeploy-server instance',
            ]),
        ]);

        $result = $this->manager()->createSnapshot(
            new CreateCloudServerSnapshotData(
                regionId: 'eu-west1-a',
                serverId: 'server-123',
                name: 'snapshot-one',
                description: 'Before deployment',
            ),
        );

        $this->assertSame(
            'snapshot-123',
            $result->snapshotId,
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->url() === self::BASE_URL
                    .'/snapshot/eu-west1-a/instance/create'
                    && $request->data() === [
                        'description' => 'Before deployment',
                        'instance_id' => 'server-123',
                        'name' => 'snapshot-one',
                    ];
            },
        );
    }

    public function test_it_lists_server_snapshot_summaries(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
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
            ]),
        ]);

        $result = $this->manager()->listSnapshots(
            'eu-west1-a',
        );

        $this->assertCount(
            1,
            $result,
        );

        $this->assertSame(
            'server-123',
            $result[0]->serverId,
        );
    }

    public function test_it_deletes_unique_snapshot_identifiers(): void
    {
        Http::fake([
            self::BASE_URL.'/*' => Http::response([
                'code' => 0,
                'message' => 'snapshot deleted',
                'errors' => [
                    [
                        'snapshot-one',
                        'snapshot-two',
                    ],
                ],
            ]),
        ]);

        $result = $this->manager()->deleteSnapshots(
            new DeleteCloudServerSnapshotsData(
                regionId: 'eu-west1-a',
                snapshotIds: [
                    'snapshot-123',
                    'snapshot-456',
                    'snapshot-123',
                ],
            ),
        );

        $this->assertSame(
            2,
            $result->deletedCount(),
        );

        Http::assertSent(
            function (Request $request): bool {
                return $request->url() === self::BASE_URL
                    .'/snapshot/eu-west1-a/delete'
                    && $request->data() === [
                        'snapshot_ids' => [
                            'snapshot-123',
                            'snapshot-456',
                        ],
                    ];
            },
        );
    }

    private function manager(): ArvanCloudSnapshotManager
    {
        return new ArvanCloudSnapshotManager(
            client: new ArvanCloudV2Client(
                baseUrl: self::BASE_URL,
                apiKey: 'test-api-key',
                connectTimeout: 5,
                requestTimeout: 15,
            ),
            mapper: new ArvanCloudSnapshotResponseMapper,
        );
    }
}
