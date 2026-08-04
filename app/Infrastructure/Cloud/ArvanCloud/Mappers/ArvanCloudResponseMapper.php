<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudQuotaData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudServerStatus;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use DateTimeImmutable;
use Exception;

final class ArvanCloudResponseMapper
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapCreatedServer(
        array $payload,
        string $regionId,
        string $defaultUsername,
        string $requestedName,
    ): CreatedCloudServerData {
        $regionId = $this->normalizeRegionId(
            $regionId,
        );

        $server = $this->createdServerObject(
            $payload,
        );

        $requestedName = trim(
            $requestedName,
        );

        if ($requestedName === '') {
            throw new CloudUnexpectedResponseException(
                'Requested cloud server name cannot be empty.',
            );
        }

        $responseName = $server['name'] ?? null;

        $name = is_string($responseName)
        && trim($responseName) !== ''
            ? trim($responseName)
            : $requestedName;

        return new CreatedCloudServerData(
            id: $this->requiredString(
                $server,
                'id',
                'created server',
            ),

            name: $name,

            regionId: $regionId,

            /*
             * A successful POST means the provisioning request was accepted.
             * The authoritative status is read later through findServer().
             */
            status: CloudServerStatus::Provisioning,

            username: $this->serverUsername(
                $server,
                $defaultUsername,
            ),

            /*
             * The create response does not guarantee a creation timestamp.
             * The authoritative value is obtained during polling.
             */
            createdAt: null,

            generatedPassword: $this->optionalString(
                $server,
                'password',
                'created server',
            ),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapServer(
        array $payload,
        string $regionId,
        string $serverId,
        string $defaultUsername,
    ): CloudServerData {
        $regionId = $this->normalizeRegionId(
            $regionId,
        );

        $serverId = trim($serverId);

        if ($serverId === '') {
            throw new CloudUnexpectedResponseException(
                'Cloud server identifier cannot be empty.',
            );
        }

        foreach (
            $this->dataList($payload, 'servers') as $server
        ) {
            $currentId = $this->requiredString(
                $server,
                'id',
                'server',
            );

            if ($currentId !== $serverId) {
                continue;
            }

            return $this->mapServerObject(
                server: $server,
                regionId: $regionId,
                defaultUsername: $defaultUsername,
            );
        }

        throw new CloudResourceNotFoundException(
            sprintf(
                'Cloud server [%s] was not found.',
                $serverId,
            ),
        );
    }

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
     * @param  array<string, mixed>  $server
     */
    private function mapServerObject(
        array $server,
        string $regionId,
        string $defaultUsername,
    ): CloudServerData {
        $status = $this->mapServerStatus(
            $this->requiredString(
                $server,
                'status',
                'server',
            ),
        );

        return new CloudServerData(
            id: $this->requiredString(
                $server,
                'id',
                'server',
            ),

            name: $this->requiredString(
                $server,
                'name',
                'server',
            ),

            regionId: $regionId,

            status: $status,

            username: $this->serverUsername(
                $server,
                $defaultUsername,
            ),

            sizeId: $this->nestedReferenceId(
                $server,
                'flavor',
                [
                    'id',
                    'name',
                ],
            ),

            imageId: $this->nestedReferenceId(
                $server,
                'image',
                [
                    'id',
                ],
            ),

            createdAt: $this->serverCreatedAt(
                $server,
                'server',
            ),

            addresses: $this->serverAddresses(
                server: $server,
                status: $status,
            ),

            networkIds: $this->serverReferenceIds(
                server: $server,
                key: 'networks',
                resource: 'server networks',
                idKeys: [
                    'id',
                    'network_id',
                    'uuid',
                ],
                status: $status,
            ),

            securityGroupIds: $this->serverReferenceIds(
                server: $server,
                key: 'security_groups',
                resource: 'server security groups',
                idKeys: [
                    'id',
                    'uuid',
                ],
                status: $status,
            ),

            volumeBacked: $this->optionalBoolean(
                $server,
                'volume_backed',
                false,
            ),

            highAvailability: $this->optionalBoolean(
                $server,
                'ha_enabled',
                false,
            ),
        );
    }

    /**
     * ArvanCloud may return incomplete address information while
     * the server is still being provisioned.
     *
     * @param  array<string, mixed>  $server
     * @return list<CloudServerAddressData>
     */
    private function serverAddresses(
        array $server,
        CloudServerStatus $status,
    ): array {
        try {
            return $this->mapServerAddresses($server);
        } catch (CloudUnexpectedResponseException $exception) {
            if ($status === CloudServerStatus::Provisioning) {
                return [];
            }

            throw $exception;
        }
    }

    /**
     * ArvanCloud may temporarily return null, an object, or another
     * incomplete shape for provider references during provisioning.
     *
     * Active servers must still satisfy the strict provider contract.
     *
     * @param  array<string, mixed>  $server
     * @param  list<string>  $idKeys
     * @return list<string>
     */
    private function serverReferenceIds(
        array $server,
        string $key,
        string $resource,
        array $idKeys,
        CloudServerStatus $status,
    ): array {
        try {
            return $this->uniqueReferenceIds(
                data: $server,
                key: $key,
                resource: $resource,
                idKeys: $idKeys,
            );
        } catch (CloudUnexpectedResponseException $exception) {
            if ($status === CloudServerStatus::Provisioning) {
                return [];
            }

            throw $exception;
        }
    }

    /**
     * پاسخ Create در Discovery ممکن است Object اصلی یا data باشد.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createdServerObject(
        array $payload,
    ): array {
        if (
            ! array_is_list($payload)
            && isset($payload['id'])
        ) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        $data = $payload['data'] ?? null;

        if (
            is_array($data)
            && ! array_is_list($data)
            && isset($data['id'])
        ) {
            /** @var array<string, mixed> $data */
            return $data;
        }

        if (
            is_array($data)
            && array_is_list($data)
            && count($data) === 1
            && is_array($data[0])
            && ! array_is_list($data[0])
            && isset($data[0]['id'])
        ) {
            /** @var array<string, mixed> $server */
            $server = $data[0];

            return $server;
        }

        throw new CloudUnexpectedResponseException(
            'ArvanCloud create server response has an invalid envelope.',
        );
    }

    private function mapServerStatus(
        string $status,
    ): CloudServerStatus {
        return match (strtoupper(trim($status))) {
            'ACTIVE' => CloudServerStatus::Active,

            'BUILD',
            'BUILDING',
            'CREATING',
            'PROVISIONING',
            'QUEUED',
            'REBUILD',
            'REBOOT',
            'HARD_REBOOT',
            'RESIZE',
            'VERIFY_RESIZE',
            'REVERT_RESIZE',
            'PASSWORD' => CloudServerStatus::Provisioning,

            'ERROR',
            'FAILED',
            'DELETED' => CloudServerStatus::Failed,

            default => CloudServerStatus::Unknown,
        };
    }

    /**
     * @param  array<string, mixed>  $server
     * @return list<CloudServerAddressData>
     */
    private function mapServerAddresses(
        array $server,
    ): array {
        if (
            ! array_key_exists('addresses', $server)
            || ! is_array($server['addresses'])
        ) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud server field [addresses] must be an array.',
            );
        }

        $groups = $server['addresses'];
        $addressItems = [];

        if (array_is_list($groups)) {
            $addressItems = $groups;
        } else {
            foreach ($groups as $group) {
                if (
                    ! is_array($group)
                    || ! array_is_list($group)
                ) {
                    throw new CloudUnexpectedResponseException(
                        'ArvanCloud server address group must be a list.',
                    );
                }

                foreach ($group as $address) {
                    $addressItems[] = $address;
                }
            }
        }

        $addresses = [];

        foreach ($addressItems as $address) {
            if (
                ! is_array($address)
                || array_is_list($address)
            ) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud server address item is invalid.',
                );
            }

            $version = $address['version'] ?? null;

            $ipVersion = match ((string) $version) {
                '4' => CloudIpVersion::IPv4,
                '6' => CloudIpVersion::IPv6,

                default => throw new CloudUnexpectedResponseException(
                    'ArvanCloud server address has an unsupported IP version.',
                ),
            };

            $addresses[] = new CloudServerAddressData(
                address: $this->requiredString(
                    $address,
                    'addr',
                    'server address',
                ),

                version: $ipVersion,

                isPublic: $this->requiredBool(
                    $address,
                    'is_public',
                    'server address',
                ),

                isVpc: $this->requiredBool(
                    $address,
                    'is_vpc',
                    'server address',
                ),

                type: $this->optionalString(
                    $address,
                    'type',
                    'server address',
                ),
            );
        }

        return $addresses;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $idKeys
     * @return list<string>
     */
    private function uniqueReferenceIds(
        array $data,
        string $key,
        string $resource,
        array $idKeys,
    ): array {
        if (! array_key_exists($key, $data)) {
            return [];
        }

        $items = $data[$key];

        if (
            ! is_array($items)
            || ! array_is_list($items)
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s must be a list.',
                    $resource,
                ),
            );
        }

        $ids = [];

        foreach ($items as $item) {
            /*
             * ArvanCloud networks are returned as a list of UUID strings.
             *
             * Example:
             * networks: ["626ad7fd-..."]
             */
            if (is_string($item)) {
                $id = trim($item);

                if ($id === '') {
                    throw new CloudUnexpectedResponseException(
                        sprintf(
                            'ArvanCloud %s contains an empty identifier.',
                            $resource,
                        ),
                    );
                }

                $ids[] = $id;

                continue;
            }

            /*
             * Security groups and some provider references are returned
             * as objects containing an ID field.
             *
             * Example:
             * security_groups: [["id" => "8449a4f5-..."]]
             */
            if (
                ! is_array($item)
                || array_is_list($item)
            ) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s contains an invalid item.',
                        $resource,
                    ),
                );
            }

            $id = $this->referenceIdFromObject(
                item: $item,
                idKeys: $idKeys,
                resource: $resource,
            );

            $ids[] = $id;
        }

        return array_values(
            array_unique($ids),
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $idKeys
     */
    private function referenceIdFromObject(
        array $item,
        array $idKeys,
        string $resource,
    ): string {
        foreach ($idKeys as $idKey) {
            if (! array_key_exists($idKey, $item)) {
                continue;
            }

            $value = $item[$idKey];

            if (! is_string($value)) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s field [%s] must be a string.',
                        $resource,
                        $idKey,
                    ),
                );
            }

            $value = trim($value);

            if ($value === '') {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s field [%s] cannot be empty.',
                        $resource,
                        $idKey,
                    ),
                );
            }

            return $value;
        }

        throw new CloudUnexpectedResponseException(
            sprintf(
                'ArvanCloud %s does not contain a valid identifier.',
                $resource,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $idKeys
     */
    private function nestedReferenceId(
        array $data,
        string $key,
        array $idKeys,
    ): ?string {
        if (! array_key_exists($key, $data)) {
            return null;
        }

        $value = $data[$key];

        if (is_string($value)) {
            $value = trim($value);

            return $value !== ''
                ? $value
                : null;
        }

        if (
            ! is_array($value)
            || array_is_list($value)
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud server field [%s] has an invalid reference.',
                    $key,
                ),
            );
        }

        foreach ($idKeys as $idKey) {
            if (
                isset($value[$idKey])
                && is_string($value[$idKey])
                && trim($value[$idKey]) !== ''
            ) {
                return trim($value[$idKey]);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $server
     */
    private function serverUsername(
        array $server,
        string $defaultUsername,
    ): string {
        $username = $this->optionalString(
            $server,
            'username',
            'server',
        );

        if ($username !== null) {
            return $username;
        }

        $defaultUsername = trim(
            $defaultUsername,
        );

        if ($defaultUsername === '') {
            throw new CloudUnexpectedResponseException(
                'Cloud image default username is missing.',
            );
        }

        return $defaultUsername;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function serverCreatedAt(
        array $data,
        string $resource,
    ): ?DateTimeImmutable {
        $value = $this->optionalString(
            $data,
            'created',
            $resource,
        );

        $value ??= $this->optionalString(
            $data,
            'created_at',
            $resource,
        );

        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new CloudUnexpectedResponseException(
                message: sprintf(
                    'ArvanCloud %s creation date is invalid.',
                    $resource,
                ),
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalBoolean(
        array $data,
        string $key,
        bool $default,
    ): bool {
        if (! array_key_exists($key, $data)) {
            return $default;
        }

        if (! is_bool($data[$key])) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud server field [%s] must be boolean.',
                    $key,
                ),
            );
        }

        return $data[$key];
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
