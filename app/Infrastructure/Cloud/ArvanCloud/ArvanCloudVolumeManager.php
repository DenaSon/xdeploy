<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\DTOs\CloudVolumeAttachmentData;
use App\Domain\Cloud\DTOs\CloudVolumeData;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use DateTimeImmutable;
use Exception;

final readonly class ArvanCloudVolumeManager implements CloudVolumeManagerInterface
{
    private const string RESOURCE_VOLUMES = 'volumes';

    public function __construct(
        private ArvanCloudClient $client,
    ) {}

    public function listVolumes(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapVolumeList(
            payload: $this->client->get(
                $this->regionEndpoint(
                    regionId: $regionId,
                    resource: self::RESOURCE_VOLUMES,
                ),
            ),
            regionId: $regionId,
        );
    }

    public function listAttachedToServer(
        string $region,
        string $serverId,
    ): array {
        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        return array_values(
            array_filter(
                $this->listVolumes($region),
                static fn (CloudVolumeData $volume): bool => $volume->isAttachedTo(
                    $providerServerId,
                ),
            ),
        );
    }

    public function findVolume(
        string $region,
        string $volumeId,
    ): CloudVolumeData {
        $regionId = $this->normalizeRegion($region);
        $providerVolumeId = $this->normalizeResourceId(
            id: $volumeId,
            resource: 'volume',
        );

        return $this->mapVolume(
            volume: $this->dataObject(
                $this->client->get(
                    $this->volumeEndpoint(
                        regionId: $regionId,
                        volumeId: $providerVolumeId,
                    ),
                ),
                'volume',
            ),
            regionId: $regionId,
        );
    }

    public function deleteVolume(
        string $region,
        string $volumeId,
    ): void {
        $regionId = $this->normalizeRegion($region);
        $providerVolumeId = $this->normalizeResourceId(
            id: $volumeId,
            resource: 'volume',
        );

        $this->client->delete(
            $this->volumeEndpoint(
                regionId: $regionId,
                volumeId: $providerVolumeId,
            ),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudVolumeData>
     */
    private function mapVolumeList(
        array $payload,
        string $regionId,
    ): array {
        $data = $payload['data'] ?? null;

        if (! is_array($data) || ! array_is_list($data)) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud volumes response field [data] must be a list.',
            );
        }

        $volumes = [];
        $seen = [];

        foreach ($data as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud volumes response contains an invalid volume.',
                );
            }

            $volume = $this->mapVolume(
                volume: $item,
                regionId: $regionId,
            );

            if (isset($seen[$volume->id])) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud volumes response contains duplicate volume identifier [%s].',
                        $volume->id,
                    ),
                );
            }

            $seen[$volume->id] = true;
            $volumes[] = $volume;
        }

        return $volumes;
    }

    /**
     * @param  array<string, mixed>  $volume
     */
    private function mapVolume(
        array $volume,
        string $regionId,
    ): CloudVolumeData {
        $id = $this->requiredString(
            data: $volume,
            key: 'id',
            resource: 'volume',
        );

        return new CloudVolumeData(
            id: $id,
            name: $this->optionalString($volume, 'name') ?? $id,
            regionId: $regionId,
            status: $this->requiredString(
                data: $volume,
                key: 'status',
                resource: 'volume',
            ),
            attachments: $this->mapAttachments(
                $volume['attachments'] ?? null,
            ),
            createdAt: $this->optionalDateTime(
                $volume['created_at'] ?? null,
                'volume.created_at',
            ),
        );
    }

    /**
     * @return list<CloudVolumeAttachmentData>
     */
    private function mapAttachments(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud volume field [attachments] must be a list or null.',
            );
        }

        $attachments = [];

        foreach ($value as $attachment) {
            if (! is_array($attachment) || array_is_list($attachment)) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud volume attachments contain an invalid entry.',
                );
            }

            $attachments[] = new CloudVolumeAttachmentData(
                id: $this->optionalString($attachment, 'id'),
                serverId: $this->requiredString(
                    data: $attachment,
                    key: 'server_id',
                    resource: 'volume attachment',
                ),
                serverName: $this->optionalString(
                    $attachment,
                    'server_name',
                ),
                device: $this->optionalString(
                    $attachment,
                    'device',
                ),
                attachedAt: $this->optionalDateTime(
                    $attachment['attached_at'] ?? null,
                    'volume attachment.attached_at',
                ),
                hostName: $this->optionalString(
                    $attachment,
                    'host_name',
                ),
            );
        }

        return $attachments;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dataObject(
        array $payload,
        string $resource,
    ): array {
        $data = $payload['data'] ?? null;

        if (! is_array($data) || array_is_list($data)) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s response field [data] must be an object.',
                    $resource,
                ),
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredString(
        array $data,
        string $key,
        string $resource,
    ): string {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a non-empty string.',
                    $resource,
                    $key,
                ),
            );
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalString(
        array $data,
        string $key,
    ): ?string {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud volume field [%s] must be a string or null.',
                    $key,
                ),
            );
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function optionalDateTime(
        mixed $value,
        string $field,
    ): ?DateTimeImmutable {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s must be an ISO-8601 timestamp or null.',
                    $field,
                ),
            );
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new CloudUnexpectedResponseException(
                message: sprintf(
                    'ArvanCloud %s contains an invalid timestamp.',
                    $field,
                ),
                previous: $exception,
            );
        }
    }

    private function regionEndpoint(
        string $regionId,
        string $resource,
    ): string {
        return sprintf(
            'regions/%s/%s',
            rawurlencode($regionId),
            rawurlencode($resource),
        );
    }

    private function volumeEndpoint(
        string $regionId,
        string $volumeId,
    ): string {
        return sprintf(
            '%s/%s',
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_VOLUMES,
            ),
            rawurlencode($volumeId),
        );
    }

    private function normalizeRegion(string $region): string
    {
        $region = trim($region);

        if (
            $region === ''
            || preg_match(
                '/\A[a-zA-Z0-9]+(?:[._-][a-zA-Z0-9]+)*\z/',
                $region,
            ) !== 1
        ) {
            throw new CloudValidationException(
                'Cloud region identifier is invalid.',
            );
        }

        return $region;
    }

    private function normalizeResourceId(
        string $id,
        string $resource,
    ): string {
        $id = trim($id);

        if (
            $id === ''
            || preg_match(
                '/\A[a-zA-Z0-9]+(?:[._-][a-zA-Z0-9]+)*\z/',
                $id,
            ) !== 1
        ) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud %s identifier is invalid.',
                    $resource,
                ),
            );
        }

        return $id;
    }
}
