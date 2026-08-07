<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudServerSnapshotManagerInterface;
use App\Domain\Cloud\DTOs\CloudServerSnapshotSummaryData;
use App\Domain\Cloud\DTOs\CreateCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\CreatedCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\DeleteCloudServerSnapshotsData;
use App\Domain\Cloud\DTOs\DeletedCloudServerSnapshotsData;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudSnapshotResponseMapper;

final readonly class ArvanCloudSnapshotManager implements CloudServerSnapshotManagerInterface
{
    public function __construct(
        private ArvanCloudV2Client $client,
        private ArvanCloudSnapshotResponseMapper $mapper,
    ) {}

    public function createSnapshot(
        CreateCloudServerSnapshotData $data,
    ): CreatedCloudServerSnapshotData {
        $request = new CreateCloudServerSnapshotData(
            regionId: $this->requiredString(
                value: $data->regionId,
                name: 'region identifier',
            ),
            serverId: $this->requiredString(
                value: $data->serverId,
                name: 'server identifier',
            ),
            name: $this->requiredString(
                value: $data->name,
                name: 'snapshot name',
            ),
            description: trim($data->description),
        );

        $payload = $this->client->post(
            path: sprintf(
                'snapshot/%s/instance/create',
                rawurlencode($request->regionId),
            ),
            payload: [
                'description' => $request->description,
                'instance_id' => $request->serverId,
                'name' => $request->name,
            ],
        );

        return $this->mapper->mapCreatedSnapshot(
            payload: $payload,
            request: $request,
        );
    }

    /**
     * @return list<CloudServerSnapshotSummaryData>
     */
    public function listSnapshots(
        string $regionId,
    ): array {
        $regionId = $this->requiredString(
            value: $regionId,
            name: 'region identifier',
        );

        return $this->mapper->mapSnapshotSummaries(
            $this->client->get(
                path: sprintf(
                    'snapshot/%s/instance/list',
                    rawurlencode($regionId),
                ),
            ),
        );
    }

    public function deleteSnapshots(
        DeleteCloudServerSnapshotsData $data,
    ): DeletedCloudServerSnapshotsData {
        $regionId = $this->requiredString(
            value: $data->regionId,
            name: 'region identifier',
        );

        $snapshotIds = $this->normalizeSnapshotIds(
            $data->snapshotIds,
        );

        $payload = $this->client->post(
            path: sprintf(
                'snapshot/%s/delete',
                rawurlencode($regionId),
            ),
            payload: [
                'snapshot_ids' => $snapshotIds,
            ],
        );

        return $this->mapper->mapDeletedSnapshots(
            $payload,
        );
    }

    private function requiredString(
        string $value,
        string $name,
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new CloudValidationException(
                sprintf(
                    'Cloud snapshot %s cannot be empty.',
                    $name,
                ),
            );
        }

        return $value;
    }

    /**
     * @param  list<string>  $snapshotIds
     * @return non-empty-list<string>
     */
    private function normalizeSnapshotIds(
        array $snapshotIds,
    ): array {
        $normalized = [];

        foreach ($snapshotIds as $snapshotId) {
            if (! is_string($snapshotId)) {
                throw new CloudValidationException(
                    'Cloud snapshot identifier must be a string.',
                );
            }

            $snapshotId = trim($snapshotId);

            if ($snapshotId === '') {
                throw new CloudValidationException(
                    'Cloud snapshot identifier cannot be empty.',
                );
            }

            $normalized[$snapshotId] = $snapshotId;
        }

        if ($normalized === []) {
            throw new CloudValidationException(
                'At least one cloud snapshot identifier is required.',
            );
        }

        return array_values($normalized);
    }
}
