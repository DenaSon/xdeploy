<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\DTOs\CloudServerSnapshotSummaryData;
use App\Domain\Cloud\DTOs\CreateCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\CreatedCloudServerSnapshotData;
use App\Domain\Cloud\DTOs\DeletedCloudServerSnapshotsData;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;

final class ArvanCloudSnapshotResponseMapper
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapCreatedSnapshot(
        array $payload,
        CreateCloudServerSnapshotData $request,
    ): CreatedCloudServerSnapshotData {
        $regionId = $this->requiredInputString(
            $request->regionId,
            'snapshot region identifier',
        );

        $serverId = $this->requiredInputString(
            $request->serverId,
            'snapshot server identifier',
        );

        $name = $this->requiredInputString(
            $request->name,
            'snapshot name',
        );

        $responseServerId = $this->requiredString(
            data: $payload,
            key: 'instance_id',
            resource: 'created snapshot',
        );

        if ($responseServerId !== $serverId) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud created snapshot belongs to unexpected server [%s].',
                    $responseServerId,
                ),
            );
        }

        return new CreatedCloudServerSnapshotData(
            regionId: $regionId,
            serverId: $serverId,
            snapshotId: $this->requiredString(
                data: $payload,
                key: 'snapshot_id',
                resource: 'created snapshot',
            ),
            name: $name,
            message: $this->requiredString(
                data: $payload,
                key: 'message',
                resource: 'created snapshot',
            ),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudServerSnapshotSummaryData>
     */
    public function mapSnapshotSummaries(
        array $payload,
    ): array {
        if (! array_key_exists('data', $payload)) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud snapshot summaries field [data] is missing.',
            );
        }

        $items = $payload['data'];

        /*
         * The live v2 endpoint returns {"data": null} when a region has
         * no instance snapshots. Domain consumers receive an empty list.
         */
        if ($items === null) {
            return [];
        }

        if (
            ! is_array($items)
            || ! array_is_list($items)
        ) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud snapshot summaries field [data] must be a list or null.',
            );
        }

        $summaries = [];

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud snapshot summary at index [%d] must be an object.',
                        $index,
                    ),
                );
            }

            $progress = $this->requiredNonNegativeInt(
                data: $item,
                key: 'progress',
                resource: 'snapshot summary',
            );

            if ($progress > 100) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud snapshot summary progress must not exceed 100.',
                );
            }

            $summaries[] = new CloudServerSnapshotSummaryData(
                serverId: $this->requiredString(
                    data: $item,
                    key: 'instance_id',
                    resource: 'snapshot summary',
                ),
                serverName: $this->requiredString(
                    data: $item,
                    key: 'instance_name',
                    resource: 'snapshot summary',
                ),
                snapshotsCount: $this->requiredNonNegativeInt(
                    data: $item,
                    key: 'snapshots_count',
                    resource: 'snapshot summary',
                ),
                status: $this->optionalString(
                    data: $item,
                    key: 'status',
                    resource: 'snapshot summary',
                ),
                progress: $progress,
                inProgressSnapshotId: $this->optionalString(
                    data: $item,
                    key: 'in_progress_snapshot_id',
                    resource: 'snapshot summary',
                ),
                inProgressSnapshotName: $this->optionalString(
                    data: $item,
                    key: 'in_progress_snapshot_name',
                    resource: 'snapshot summary',
                ),
            );
        }

        return $summaries;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapDeletedSnapshots(
        array $payload,
    ): DeletedCloudServerSnapshotsData {
        if (
            ! array_key_exists('code', $payload)
            || ! is_int($payload['code'])
        ) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud snapshot deletion field [code] must be an integer.',
            );
        }

        if ($payload['code'] !== 0) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud snapshot deletion returned failure code [%d].',
                    $payload['code'],
                ),
            );
        }

        return new DeletedCloudServerSnapshotsData(
            message: $this->requiredString(
                data: $payload,
                key: 'message',
                resource: 'snapshot deletion',
            ),
            snapshotNames: $this->deletedSnapshotNames(
                $payload,
            ),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<string>
     */
    private function deletedSnapshotNames(
        array $payload,
    ): array {
        if (! array_key_exists('errors', $payload)) {
            return [];
        }

        $errors = $payload['errors'];

        if ($errors === null) {
            return [];
        }

        if (! is_array($errors)) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud snapshot deletion field [errors] must be an array or null.',
            );
        }

        $names = [];

        $this->collectStrings(
            value: $errors,
            names: $names,
        );

        return array_values(
            array_unique($names),
        );
    }

    /**
     * @param  list<string>  $names
     */
    private function collectStrings(
        mixed $value,
        array &$names,
    ): void {
        if (is_string($value)) {
            $value = trim($value);

            if ($value !== '') {
                $names[] = $value;
            }

            return;
        }

        if (! is_array($value)) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud snapshot deletion field [errors] contains an unexpected value.',
            );
        }

        foreach ($value as $item) {
            $this->collectStrings(
                value: $item,
                names: $names,
            );
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function requiredString(
        array $data,
        string $key,
        string $resource,
    ): string {
        if (
            ! array_key_exists($key, $data)
            || ! is_string($data[$key])
            || trim($data[$key]) === ''
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a non-empty string.',
                    $resource,
                    $key,
                ),
            );
        }

        return trim($data[$key]);
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function optionalString(
        array $data,
        string $key,
        string $resource,
    ): ?string {
        if (! array_key_exists($key, $data)) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] is missing.',
                    $resource,
                    $key,
                ),
            );
        }

        if ($data[$key] === null) {
            return null;
        }

        if (! is_string($data[$key])) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a string or null.',
                    $resource,
                    $key,
                ),
            );
        }

        $value = trim($data[$key]);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function requiredNonNegativeInt(
        array $data,
        string $key,
        string $resource,
    ): int {
        if (
            ! array_key_exists($key, $data)
            || ! is_int($data[$key])
            || $data[$key] < 0
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a non-negative integer.',
                    $resource,
                    $key,
                ),
            );
        }

        return $data[$key];
    }

    private function requiredInputString(
        string $value,
        string $name,
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'Requested %s cannot be empty.',
                    $name,
                ),
            );
        }

        return $value;
    }
}
