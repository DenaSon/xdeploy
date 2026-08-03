<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudQuotaData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;

final class ArvanCloudResponseMapper
{
    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudRegionData>
     */
    public function mapRegions(array $payload): array
    {
        $regions = $this->dataList($payload, 'regions');

        return array_map(
            function (array $region): CloudRegionData {
                $country = $this->requiredString(
                    $region,
                    'country',
                    'region',
                );

                $city = $this->requiredString(
                    $region,
                    'city',
                    'region',
                );

                $dataCenter = $this->requiredString(
                    $region,
                    'dc',
                    'region',
                );

                return new CloudRegionData(
                    id: $this->requiredString(
                        $region,
                        'code',
                        'region',
                    ),
                    displayName: implode(
                        ' / ',
                        [
                            $country,
                            $city,
                            $dataCenter,
                        ],
                    ),
                    country: $country,
                    city: $city,
                    dataCenter: $dataCenter,
                    canCreateServers: $this->requiredBool(
                        $region,
                        'create',
                        'region',
                    ),
                    isVisible: $this->requiredBool(
                        $region,
                        'visible',
                        'region',
                    ),
                    supportsVolumeBacked: $this->requiredBool(
                        $region,
                        'volume_backed',
                        'region',
                    ),
                );
            },
            $regions,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudSizeData>
     */
    public function mapSizes(
        array $payload,
        string $regionId,
    ): array {
        $regionId = $this->normalizeRegionId($regionId);
        $sizes = $this->dataList($payload, 'sizes');

        return array_map(
            function (array $size) use ($regionId): CloudSizeData {
                $availabilityZone = $this->requiredString(
                    $size,
                    'availabilityZone',
                    'size',
                );

                if ($availabilityZone !== $regionId) {
                    throw new CloudUnexpectedResponseException(
                        sprintf(
                            'ArvanCloud size belongs to unexpected region [%s].',
                            $availabilityZone,
                        ),
                    );
                }

                return new CloudSizeData(
                    id: $this->requiredString(
                        $size,
                        'id',
                        'size',
                    ),
                    name: $this->requiredString(
                        $size,
                        'name',
                        'size',
                    ),
                    regionId: $regionId,
                    vCpu: $this->requiredNonNegativeInt(
                        $size,
                        'cpu_count',
                        'size',
                    ),
                    memoryMiB: $this->bytesToMiB(
                        $this->requiredNonNegativeInt(
                            $size,
                            'memory_in_bytes',
                            'size',
                        ),
                        'size.memory_in_bytes',
                    ),
                    diskGiB: $this->bytesToGiB(
                        $this->requiredNonNegativeInt(
                            $size,
                            'disk_in_bytes',
                            'size',
                        ),
                        'size.disk_in_bytes',
                    ),
                    category: $this->optionalString(
                        $size,
                        'type',
                        'size',
                    ),
                    hourlyPrice: new CloudPriceData(
                        amount: (string) $this->requiredNonNegativeInt(
                            $size,
                            'price_per_hour',
                            'size',
                        ),
                        currencyCode: null,
                        billingPeriod: CloudBillingPeriod::Hourly,
                    ),
                    monthlyPrice: new CloudPriceData(
                        amount: (string) $this->requiredNonNegativeInt(
                            $size,
                            'price_per_month',
                            'size',
                        ),
                        currencyCode: null,
                        billingPeriod: CloudBillingPeriod::Monthly,
                    ),
                );
            },
            $sizes,
        );
    }

    public function mapImages(
        array $payload,
        string $regionId,
    ): array {
        $regionId = $this->normalizeRegionId($regionId);
        $groups = $this->dataList($payload, 'image groups');

        $images = [];

        foreach ($groups as $group) {
            $groupImages = $this->requiredList(
                $group,
                'images',
                'image group',
            );

            foreach ($groupImages as $image) {
                $distribution = $this->requiredString(
                    $image,
                    'distribution_name',
                    'image',
                );

                $distribution = ucfirst(
                    strtolower($distribution),
                );

                $version = $this->requiredString(
                    $image,
                    'name',
                    'image',
                );

                $disk = $this->requiredNonNegativeInt(
                    $image,
                    'disk',
                    'image',
                );

                $ram = $this->requiredNonNegativeInt(
                    $image,
                    'ram',
                    'image',
                );

                $images[] = new CloudImageData(
                    id: $this->requiredString(
                        $image,
                        'id',
                        'image',
                    ),
                    name: "{$distribution} {$version}",
                    regionId: $regionId,
                    distribution: $distribution,
                    version: $version,
                    architecture: null,
                    minDiskGiB: $disk > 0 ? $disk : null,
                    minMemoryMiB: $ram > 0 ? $ram : null,
                    supportsSshKey: $this->requiredBool(
                        $image,
                        'ssh_key',
                        'image',
                    ),
                    supportsPassword: $this->requiredBool(
                        $image,
                        'ssh_password',
                        'image',
                    ),
                );
            }
        }

        return $images;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudNetworkData>
     */
    public function mapNetworks(
        array $payload,
        string $regionId,
    ): array {
        $regionId = $this->normalizeRegionId($regionId);
        $networks = $this->dataList($payload, 'networks');

        return array_map(
            function (array $network) use ($regionId): CloudNetworkData {
                $subnets = $this->requiredList(
                    $network,
                    'subnets',
                    'network',
                );

                if ($subnets === []) {
                    throw new CloudUnexpectedResponseException(
                        'ArvanCloud network does not contain a subnet.',
                    );
                }

                $subnet = $subnets[0];

                return new CloudNetworkData(
                    id: $this->requiredString(
                        $network,
                        'id',
                        'network',
                    ),
                    name: $this->requiredString(
                        $network,
                        'name',
                        'network',
                    ),
                    regionId: $regionId,
                    ipVersion: $this->mapIpVersion(
                        $this->requiredString(
                            $subnet,
                            'ip_version',
                            'network subnet',
                        ),
                    ),
                    cidr: $this->optionalString(
                        $subnet,
                        'cidr',
                        'network subnet',
                    ),
                    gateway: $this->optionalString(
                        $subnet,
                        'gateway_ip',
                        'network subnet',
                    ),
                    isActive: strtoupper(
                        $this->requiredString(
                            $network,
                            'status',
                            'network',
                        ),
                    ) === 'ACTIVE',
                    dhcpEnabled: $this->requiredBool(
                        $subnet,
                        'enable_dhcp',
                        'network subnet',
                    ),
                );
            },
            $networks,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudSecurityGroupData>
     */
    public function mapSecurityGroups(
        array $payload,
        string $regionId,
    ): array {
        $regionId = $this->normalizeRegionId($regionId);
        $groups = $this->dataList(
            $payload,
            'security groups',
        );

        return array_map(
            fn (array $group): CloudSecurityGroupData => new CloudSecurityGroupData(
                id: $this->requiredString(
                    $group,
                    'id',
                    'security group',
                ),
                name: $this->requiredString(
                    $group,
                    'name',
                    'security group',
                ),
                regionId: $regionId,
                description: $this->optionalString(
                    $group,
                    'description',
                    'security group',
                ),
                isDefault: $this->requiredBool(
                    $group,
                    'default',
                    'security group',
                ),
                isReadOnly: $this->requiredBool(
                    $group,
                    'readonly',
                    'security group',
                ),
            ),
            $groups,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapQuota(
        array $payload,
        string $regionId,
    ): CloudQuotaData {
        $regionId = $this->normalizeRegionId($regionId);
        $data = $this->dataObject($payload, 'quota');

        return new CloudQuotaData(
            regionId: $regionId,
            instancesLimit: $this->quotaLimit(
                $data,
                'max_total_instances',
            ),
            instancesUsed: $this->requiredNonNegativeInt(
                $data,
                'total_instances_used',
                'quota',
            ),
            vCpuLimit: $this->quotaLimit(
                $data,
                'max_total_cores',
            ),
            vCpuUsed: $this->requiredNonNegativeInt(
                $data,
                'total_cores_used',
                'quota',
            ),
            memoryMiBLimit: $this->quotaLimit(
                $data,
                'max_total_ram_size',
            ),
            memoryMiBUsed: $this->requiredNonNegativeInt(
                $data,
                'total_ram_used',
                'quota',
            ),
            sshKeysLimit: $this->quotaLimit(
                $data,
                'max_total_keypairs',
            ),
            sshKeysUsed: null,
        );
    }

    /**
     * Schema موفق این Endpoint هنوز با API Key فعلی تأیید نشده است.
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<never>
     */
    public function mapSshKeys(
        array $payload,
        string $regionId,
    ): array {
        throw new CloudUnexpectedResponseException(
            'ArvanCloud SSH key response schema has not been verified.',
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function dataList(
        array $payload,
        string $resource,
    ): array {
        if (
            ! array_key_exists('data', $payload)
            || ! is_array($payload['data'])
            || ! array_is_list($payload['data'])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s response has an invalid data envelope.',
                    $resource,
                ),
            );
        }

        foreach ($payload['data'] as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s response contains an invalid item.',
                        $resource,
                    ),
                );
            }
        }

        /** @var list<array<string, mixed>> $data */
        $data = $payload['data'];

        return $data;
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function dataObject(
        array $payload,
        string $resource,
    ): array {
        if (
            ! array_key_exists('data', $payload)
            || ! is_array($payload['data'])
            || array_is_list($payload['data'])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s response has an invalid data envelope.',
                    $resource,
                ),
            );
        }

        /** @var array<string, mixed> $data */
        $data = $payload['data'];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function requiredList(
        array $data,
        string $key,
        string $resource,
    ): array {
        if (
            ! array_key_exists($key, $data)
            || ! is_array($data[$key])
            || ! array_is_list($data[$key])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a list.',
                    $resource,
                    $key,
                ),
            );
        }

        foreach ($data[$key] as $item) {
            if (! is_array($item) || array_is_list($item)) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s field [%s] contains an invalid item.',
                        $resource,
                        $key,
                    ),
                );
            }
        }

        /** @var list<array<string, mixed>> $items */
        $items = $data[$key];

        return $items;
    }

    /**
     * @param  array<string, mixed>  $data
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
     * @param  array<string, mixed>  $data
     */
    private function optionalString(
        array $data,
        string $key,
        string $resource,
    ): ?string {
        if (
            ! array_key_exists($key, $data)
            || $data[$key] === null
            || $data[$key] === ''
        ) {
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

        return $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredBool(
        array $data,
        string $key,
        string $resource,
    ): bool {
        if (
            ! array_key_exists($key, $data)
            || ! is_bool($data[$key])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be boolean.',
                    $resource,
                    $key,
                ),
            );
        }

        return $data[$key];
    }

    /**
     * @param  array<string, mixed>  $data
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function quotaLimit(
        array $data,
        string $key,
    ): ?int {
        if (
            ! array_key_exists($key, $data)
            || ! is_array($data[$key])
            || array_is_list($data[$key])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud quota field [%s] must be an object.',
                    $key,
                ),
            );
        }

        /** @var array<string, mixed> $limit */
        $limit = $data[$key];

        $unlimited = $this->requiredBool(
            $limit,
            'unlimited',
            "quota.{$key}",
        );

        if ($unlimited) {
            return null;
        }

        if (
            ! array_key_exists('value', $limit)
            || ! is_int($limit['value'])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud quota field [%s.value] must be integer.',
                    $key,
                ),
            );
        }

        return $limit['value'] >= 0
            ? $limit['value']
            : null;
    }

    private function mapIpVersion(
        string $version,
    ): CloudIpVersion {
        return match ($version) {
            '4' => CloudIpVersion::IPv4,
            '6' => CloudIpVersion::IPv6,

            default => throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud returned unsupported IP version [%s].',
                    $version,
                ),
            ),
        };
    }

    private function bytesToMiB(
        int $bytes,
        string $field,
    ): int {
        if ($bytes % 1_048_576 !== 0) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud field [%s] is not an exact MiB value.',
                    $field,
                ),
            );
        }

        return intdiv($bytes, 1_048_576);
    }

    private function bytesToGiB(
        int $bytes,
        string $field,
    ): int {
        if ($bytes % 1_073_741_824 !== 0) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud field [%s] is not an exact GiB value.',
                    $field,
                ),
            );
        }

        return intdiv($bytes, 1_073_741_824);
    }

    private function normalizeRegionId(
        string $regionId,
    ): string {
        $regionId = trim($regionId);

        if ($regionId === '') {
            throw new CloudUnexpectedResponseException(
                'Cloud region identifier cannot be empty.',
            );
        }

        return $regionId;
    }
}
