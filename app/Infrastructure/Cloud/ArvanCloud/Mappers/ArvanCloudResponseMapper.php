<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud\Mappers;

use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudPortData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudQuotaData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudReportChartData;
use App\Domain\Cloud\DTOs\CloudReportSeriesData;
use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\DTOs\CloudServerAddressData;
use App\Domain\Cloud\DTOs\CloudServerConsoleData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudServerReportsData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Enums\CloudReportMetric;
use App\Domain\Cloud\Enums\CloudReportPeriod;
use App\Domain\Cloud\Enums\CloudServerPowerState;
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

        $serverId = trim(
            $serverId,
        );

        if ($serverId === '') {
            throw new CloudUnexpectedResponseException(
                'Cloud server identifier cannot be empty.',
            );
        }

        return $this->mapServerObject(
            server: $this->serverObject(
                payload: $payload,
                serverId: $serverId,
            ),
            regionId: $regionId,
            defaultUsername: $defaultUsername,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapServerVnc(
        array $payload,
    ): CloudServerConsoleData {
        $console = $this->serverVncObject(
            $payload,
        );

        $url = $this->requiredString(
            data: $console,
            key: 'url',
            resource: 'server VNC console',
        );

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud server VNC console field [url] must be a valid URL.',
            );
        }

        $parts = parse_url(
            $url,
        );

        if (
            ! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || ! is_string($parts['host'])
            || trim($parts['host']) === ''
        ) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud server VNC console field [url] must be an absolute HTTPS URL.',
            );
        }

        return new CloudServerConsoleData(
            url: $url,
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudServerData>
     */
    public function mapServers(
        array $payload,
        string $regionId,
        string $defaultUsername,
    ): array {
        $regionId = $this->normalizeRegionId(
            $regionId,
        );

        $servers = $this->dataList(
            payload: $payload,
            resource: 'servers',
        );

        $mappedServers = [];
        $seenServerIds = [];

        foreach ($servers as $server) {
            $serverId = $this->requiredString(
                data: $server,
                key: 'id',
                resource: 'server',
            );

            if (isset($seenServerIds[$serverId])) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud servers response contains duplicate server identifier [%s].',
                        $serverId,
                    ),
                );
            }

            $seenServerIds[$serverId] = true;

            $mappedServers[] = $this->mapServerObject(
                server: $server,
                regionId: $regionId,
                defaultUsername: $defaultUsername,
            );
        }

        return $mappedServers;
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

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudSizeData>
     */
    public function mapServerResizePlans(
        array $payload,
        string $regionId,
    ): array {
        $regionId = $this->normalizeRegionId(
            $regionId,
        );

        return array_map(
            fn (array $size): CloudSizeData => $this->mapResizeSizeObject(
                size: $size,
                regionId: $regionId,
                resource: 'server resize plan',
            ),
            $this->resourceItems(
                payload: $payload,
                resource: 'server resize plans',
            ),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapSize(
        array $payload,
        string $regionId,
    ): CloudSizeData {
        $regionId = $this->normalizeRegionId(
            $regionId,
        );

        return $this->mapResizeSizeObject(
            size: $this->resourceObject(
                payload: $payload,
                resource: 'size',
            ),
            regionId: $regionId,
            resource: 'size',
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapCalculatedSize(
        array $payload,
        string $regionId,
    ): CloudSizeData {
        $regionId = $this->normalizeRegionId(
            $regionId,
        );

        return $this->mapResizeSizeObject(
            size: $this->resourceObject(
                payload: $payload,
                resource: 'calculated size',
            ),
            regionId: $regionId,
            resource: 'calculated size',
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapDiskPrice(
        array $payload,
    ): CloudDiskPriceData {
        $diskPrice = $this->resourceObject(
            payload: $payload,
            resource: 'disk price',
        );

        return new CloudDiskPriceData(
            diskGiB: $this->requiredFlexibleNonNegativeInt(
                data: $diskPrice,
                key: 'disk',
                resource: 'disk price',
            ),
            hourlyPrice: new CloudPriceData(
                amount: $this->requiredNonNegativeDecimalString(
                    data: $diskPrice,
                    key: 'price_per_hour',
                    resource: 'disk price',
                ),
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: $this->requiredNonNegativeDecimalString(
                    data: $diskPrice,
                    key: 'price_per_month',
                    resource: 'disk price',
                ),
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapRootPasswordReset(
        array $payload,
    ): CloudRootPasswordResetData {
        $data = $this->dataObject(
            payload: $payload,
            resource: 'root password reset',
        );

        if (
            ! array_key_exists('password', $data)
            || ! is_string($data['password'])
            || trim($data['password']) === ''
        ) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud root password reset field [password] must be a non-empty string.',
            );
        }

        /*
         * The generated credential is returned exactly as received.
         * Trimming a password could silently change the credential.
         */
        $password = $data['password'];

        return new CloudRootPasswordResetData(
            password: $password,
            message: $this->requiredString(
                data: $payload,
                key: 'message',
                resource: 'root password reset',
            ),
        );
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public function mapServerReports(
        array $payload,
        string $regionId,
        string $serverId,
        CloudReportPeriod $period,
    ): CloudServerReportsData {
        $regionId = $this->normalizeRegionId(
            $regionId,
        );

        $serverId = trim(
            $serverId,
        );

        if ($serverId === '') {
            throw new CloudUnexpectedResponseException(
                'Cloud server identifier cannot be empty.',
            );
        }

        $data = $this->dataObject(
            payload: $payload,
            resource: 'server reports',
        );

        $charts = $this->requiredObject(
            data: $data,
            key: 'charts',
            resource: 'server reports',
        );

        $statistics = $this->requiredArrayList(
            data: $charts,
            key: 'statistics',
            resource: 'server reports charts',
        );

        if ($statistics !== []) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud server report statistics schema has not been verified.',
            );
        }

        return new CloudServerReportsData(
            regionId: $regionId,
            serverId: $serverId,
            period: $period,
            cpu: $this->mapReportChart(
                charts: $charts,
                chartKey: 'cpu',
                expectedMetrics: [
                    CloudReportMetric::CpuUsage,
                ],
            ),
            ram: $this->mapReportChart(
                charts: $charts,
                chartKey: 'ram',
                expectedMetrics: [
                    CloudReportMetric::RamUsage,
                ],
            ),
            network: $this->mapReportChart(
                charts: $charts,
                chartKey: 'network',
                expectedMetrics: [
                    CloudReportMetric::NetworkIncoming,
                    CloudReportMetric::NetworkOutgoing,
                ],
            ),
            disk: $this->mapReportChart(
                charts: $charts,
                chartKey: 'disk',
                expectedMetrics: [
                    CloudReportMetric::DiskRead,
                    CloudReportMetric::DiskWrite,
                ],
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $charts
     * @param  list<CloudReportMetric>  $expectedMetrics
     */
    private function mapReportChart(
        array $charts,
        string $chartKey,
        array $expectedMetrics,
    ): CloudReportChartData {
        $resource = sprintf(
            'server report %s chart',
            $chartKey,
        );

        $chart = $this->requiredObject(
            data: $charts,
            key: $chartKey,
            resource: 'server reports charts',
        );

        /*
         * The provider title is intentionally validated but not exposed.
         * It is a translation key specific to ArvanCloud, not Domain data.
         */
        $this->requiredString(
            data: $chart,
            key: 'title',
            resource: $resource,
        );

        $timestamps = $this->reportTimestamps(
            data: $chart,
            key: 'categories',
            resource: $resource,
        );

        $seriesItems = $this->reportSeriesItems(
            data: $chart,
            key: 'series',
            resource: $resource,
        );

        /*
         * ArvanCloud may expose a timestamp before the metric series is
         * available for a newly provisioned server. In that transient state
         * it returns series as null (or occasionally an empty list).
         *
         * The orphan timestamp is not a usable report point, so the Domain
         * receives an empty chart instead of a chart with fake data.
         */
        if ($seriesItems === []) {
            return new CloudReportChartData(
                timestamps: [],
                series: [],
            );
        }

        $series = [];
        $seenMetrics = [];

        foreach ($seriesItems as $seriesItem) {
            $metric = $this->mapReportMetric(
                $this->requiredString(
                    data: $seriesItem,
                    key: 'name',
                    resource: "{$resource} series",
                ),
            );

            if (! in_array($metric, $expectedMetrics, true)) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s contains unexpected metric [%s].',
                        $resource,
                        $metric->value,
                    ),
                );
            }

            if (isset($seenMetrics[$metric->value])) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s contains duplicate metric [%s].',
                        $resource,
                        $metric->value,
                    ),
                );
            }

            $values = $this->reportNumericValues(
                data: $seriesItem,
                key: 'data',
                resource: "{$resource} series",
            );

            if (count($values) !== count($timestamps)) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s metric [%s] point count does not match its timestamps.',
                        $resource,
                        $metric->value,
                    ),
                );
            }

            $seenMetrics[$metric->value] = true;

            $series[] = new CloudReportSeriesData(
                metric: $metric,
                values: $values,
            );
        }

        if ($timestamps !== []) {
            foreach ($expectedMetrics as $expectedMetric) {
                if (! isset($seenMetrics[$expectedMetric->value])) {
                    throw new CloudUnexpectedResponseException(
                        sprintf(
                            'ArvanCloud %s is missing metric [%s].',
                            $resource,
                            $expectedMetric->value,
                        ),
                    );
                }
            }
        }

        return new CloudReportChartData(
            timestamps: $timestamps,
            series: $series,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function reportSeriesItems(
        array $data,
        string $key,
        string $resource,
    ): array {
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
            return [];
        }

        if (
            ! is_array($data[$key])
            || ! array_is_list($data[$key])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a list or null.',
                    $resource,
                    $key,
                ),
            );
        }

        foreach ($data[$key] as $item) {
            if (
                ! is_array($item)
                || array_is_list($item)
            ) {
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

    private function mapReportMetric(
        string $providerMetric,
    ): CloudReportMetric {
        return match (strtolower(trim($providerMetric))) {
            'iaas.reports.cpu' => CloudReportMetric::CpuUsage,
            'iaas.reports.ram' => CloudReportMetric::RamUsage,
            'iaas.reports.networkincoming' => CloudReportMetric::NetworkIncoming,
            'iaas.reports.networkoutgoing' => CloudReportMetric::NetworkOutgoing,
            'iaas.reports.diskread' => CloudReportMetric::DiskRead,
            'iaas.reports.diskwrite' => CloudReportMetric::DiskWrite,

            default => throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud returned unsupported report metric [%s].',
                    $providerMetric,
                ),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<DateTimeImmutable>
     */
    private function reportTimestamps(
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
                    'ArvanCloud %s field [%s] must be a list of ISO-8601 timestamps.',
                    $resource,
                    $key,
                ),
            );
        }

        $timestamps = [];

        foreach ($data[$key] as $timestamp) {
            if (
                ! is_string($timestamp)
                || preg_match(
                    '/\\A\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2}(?:\\.\\d+)?(?:Z|[+-]\\d{2}:\\d{2})\\z/',
                    $timestamp,
                ) !== 1
            ) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s contains an invalid timestamp.',
                        $resource,
                    ),
                );
            }

            try {
                $timestamps[] = new DateTimeImmutable(
                    $timestamp,
                );
            } catch (Exception $exception) {
                throw new CloudUnexpectedResponseException(
                    message: sprintf(
                        'ArvanCloud %s contains an invalid timestamp.',
                        $resource,
                    ),
                    previous: $exception,
                );
            }
        }

        return $timestamps;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int|float>
     */
    private function reportNumericValues(
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
                    'ArvanCloud %s field [%s] must be a list of non-negative numbers.',
                    $resource,
                    $key,
                ),
            );
        }

        $values = [];

        foreach ($data[$key] as $value) {
            if (
                (! is_int($value) && ! is_float($value))
                || (is_float($value) && ! is_finite($value))
                || $value < 0
            ) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s field [%s] contains an invalid value.',
                        $resource,
                        $key,
                    ),
                );
            }

            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function requiredObject(
        array $data,
        string $key,
        string $resource,
    ): array {
        if (
            ! array_key_exists($key, $data)
            || ! is_array($data[$key])
            || array_is_list($data[$key])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be an object.',
                    $resource,
                    $key,
                ),
            );
        }

        /** @var array<string, mixed> $object */
        $object = $data[$key];

        return $object;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<mixed>
     */
    private function requiredArrayList(
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

        /** @var list<mixed> $items */
        $items = $data[$key];

        return $items;
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
     * @return list<CloudServerActionData>
     */
    public function mapServerActions(
        array $payload,
    ): array {
        $actions = $this->serverActionItems(
            $payload,
        );

        return array_map(
            fn (array $action): CloudServerActionData => new CloudServerActionData(
                action: $this->requiredString(
                    $action,
                    'action',
                    'server action',
                ),
                message: $this->optionalString(
                    $action,
                    'message',
                    'server action',
                ),
                startedAt: $this->optionalString(
                    $action,
                    'start_time',
                    'server action',
                ),
            ),
            $actions,
        );
    }

    /**
     * مستندات آروان پاسخ را به‌صورت یک Object نمایش می‌دهد،
     * اما خروجی Domain همیشه یک List استاندارد است.
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function serverActionItems(
        array $payload,
    ): array {
        $data = $payload;

        if (array_key_exists('data', $payload)) {
            if (! is_array($payload['data'])) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud server actions response has an invalid data envelope.',
                );
            }

            $data = $payload['data'];
        }

        if ($data === []) {
            return [];
        }

        if (! array_is_list($data)) {
            /** @var array<string, mixed> $data */
            return [
                $data,
            ];
        }

        foreach ($data as $action) {
            if (
                ! is_array($action)
                || array_is_list($action)
            ) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud server actions response contains an invalid item.',
                );
            }
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /**
     * The verified response uses a data object while the OpenAPI example
     * exposes the console object directly. Both shapes are supported.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function serverVncObject(
        array $payload,
    ): array {
        $candidate = array_key_exists(
            'data',
            $payload,
        )
            ? $payload['data']
            : $payload;

        if (
            ! is_array($candidate)
            || array_is_list($candidate)
        ) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud server VNC console response has an invalid data envelope.',
            );
        }

        /** @var array<string, mixed> $candidate */
        return $candidate;
    }

    /**
     * GetServerDetails normally returns one server object directly.
     *
     * This parser also accepts the previous list response and an optional
     * data envelope so existing tests and discovery responses remain valid.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function serverObject(
        array $payload,
        string $serverId,
    ): array {
        $candidate = array_key_exists(
            'data',
            $payload,
        )
            ? $payload['data']
            : $payload;

        if (! is_array($candidate)) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud server response has an invalid data envelope.',
            );
        }

        if ($candidate === []) {
            throw new CloudResourceNotFoundException(
                sprintf(
                    'Cloud server [%s] was not found.',
                    $serverId,
                ),
            );
        }

        if (! array_is_list($candidate)) {
            if (! array_key_exists('id', $candidate)) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud server response has an invalid data envelope.',
                );
            }

            /** @var array<string, mixed> $candidate */
            $server = $candidate;

            $this->assertExpectedServerId(
                server: $server,
                serverId: $serverId,
            );

            return $server;
        }

        foreach ($candidate as $item) {
            if (
                ! is_array($item)
                || array_is_list($item)
            ) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud servers response contains an invalid item.',
                );
            }

            /** @var array<string, mixed> $item */
            $currentId = $this->requiredString(
                $item,
                'id',
                'server',
            );

            if ($currentId === $serverId) {
                return $item;
            }
        }

        throw new CloudResourceNotFoundException(
            sprintf(
                'Cloud server [%s] was not found.',
                $serverId,
            ),
        );
    }

    /**
     * @param  array<string, mixed>  $server
     */
    private function assertExpectedServerId(
        array $server,
        string $serverId,
    ): void {
        $responseServerId = $this->requiredString(
            $server,
            'id',
            'server',
        );

        if ($responseServerId === $serverId) {
            return;
        }

        throw new CloudResourceNotFoundException(
            sprintf(
                'Cloud server [%s] was not found.',
                $serverId,
            ),
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
        $providerStatus = $this->requiredString(
            $server,
            'status',
            'server',
        );

        $status = $this->mapServerStatus(
            $providerStatus,
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

            sizeName: $this->nestedOptionalString(
                data: $server,
                objectKey: 'flavor',
                valueKey: 'name',
                resource: 'server flavor',
            ),

            vCpu: $this->nestedOptionalNonNegativeInt(
                data: $server,
                objectKey: 'flavor',
                valueKey: 'vcpus',
                resource: 'server flavor',
            ),

            memoryMiB: $this->nestedOptionalNonNegativeInt(
                data: $server,
                objectKey: 'flavor',
                valueKey: 'ram',
                resource: 'server flavor',
            ),

            diskGiB: $this->nestedOptionalNonNegativeInt(
                data: $server,
                objectKey: 'flavor',
                valueKey: 'disk',
                resource: 'server flavor',
            ),

            taskState: $this->optionalString(
                $server,
                'task_state',
                'server',
            ),

            providerError: $this->optionalString(
                $server,
                'error',
                'server',
            ),

            powerState: $this->mapServerPowerState(
                $providerStatus,
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
        return match ($this->normalizeProviderStatus($status)) {
            'ACTIVE',
            'SHUTOFF',
            'STOPPED',
            'PAUSED',
            'SUSPENDED',
            'POWERING_ON',
            'POWERING_OFF',
            'STARTING',
            'STOPPING',
            'REBUILD',
            'REBOOT',
            'HARD_REBOOT',
            'RESIZE',
            'VERIFY_RESIZE',
            'REVERT_RESIZE',
            'PASSWORD' => CloudServerStatus::Active,

            'BUILD',
            'BUILDING',
            'CREATING',
            'PROVISIONING',
            'QUEUED' => CloudServerStatus::Provisioning,

            'ERROR',
            'FAILED',
            'DELETED',
            'TERMINATED' => CloudServerStatus::Failed,

            default => CloudServerStatus::Unknown,
        };
    }

    private function mapServerPowerState(
        string $status,
    ): CloudServerPowerState {
        return match ($this->normalizeProviderStatus($status)) {
            'ACTIVE' => CloudServerPowerState::Running,

            'SHUTOFF',
            'STOPPED',
            'PAUSED',
            'SUSPENDED' => CloudServerPowerState::Stopped,

            'BUILD',
            'BUILDING',
            'CREATING',
            'PROVISIONING',
            'QUEUED',
            'POWERING_ON',
            'POWERING_OFF',
            'STARTING',
            'STOPPING',
            'REBUILD',
            'REBOOT',
            'HARD_REBOOT',
            'RESIZE',
            'VERIFY_RESIZE',
            'REVERT_RESIZE',
            'PASSWORD' => CloudServerPowerState::Transitioning,

            'ERROR',
            'FAILED',
            'DELETED',
            'TERMINATED' => CloudServerPowerState::Error,

            default => CloudServerPowerState::Unknown,
        };
    }

    private function normalizeProviderStatus(
        string $status,
    ): string {
        return strtoupper(
            trim(
                $status,
            ),
        );
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
     * @param  array<string, mixed>  $data
     */
    private function nestedOptionalString(
        array $data,
        string $objectKey,
        string $valueKey,
        string $resource,
    ): ?string {
        $object = $this->optionalNestedObject(
            data: $data,
            key: $objectKey,
            resource: $resource,
        );

        if ($object === null) {
            return null;
        }

        return $this->optionalString(
            $object,
            $valueKey,
            $resource,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nestedOptionalNonNegativeInt(
        array $data,
        string $objectKey,
        string $valueKey,
        string $resource,
    ): ?int {
        $object = $this->optionalNestedObject(
            data: $data,
            key: $objectKey,
            resource: $resource,
        );

        if ($object === null) {
            return null;
        }

        return $this->optionalNonNegativeInt(
            data: $object,
            key: $valueKey,
            resource: $resource,
        );
    }

    /**
     * @param  array<string, mixed>  $size
     */
    private function mapResizeSizeObject(
        array $size,
        string $regionId,
        string $resource,
    ): CloudSizeData {
        $availabilityZone = $this->optionalString(
            data: $size,
            key: 'availabilityZone',
            resource: $resource,
        );

        if (
            $availabilityZone !== null
            && $availabilityZone !== $regionId
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s belongs to unexpected region [%s].',
                    $resource,
                    $availabilityZone,
                ),
            );
        }

        $category = $this->optionalString(
            data: $size,
            key: 'type',
            resource: $resource,
        );

        $category ??= $this->optionalString(
            data: $size,
            key: 'category',
            resource: $resource,
        );

        return new CloudSizeData(
            id: $this->requiredString(
                data: $size,
                key: 'id',
                resource: $resource,
            ),
            name: $this->requiredString(
                data: $size,
                key: 'name',
                resource: $resource,
            ),
            regionId: $regionId,
            vCpu: $this->requiredFlexibleNonNegativeInt(
                data: $size,
                key: 'cpu_count',
                resource: $resource,
            ),
            memoryMiB: $this->sizeMemoryMiB(
                size: $size,
                resource: $resource,
            ),
            diskGiB: $this->sizeDiskGiB(
                size: $size,
                resource: $resource,
            ),
            category: $category,
            hourlyPrice: new CloudPriceData(
                amount: $this->requiredNonNegativeDecimalString(
                    data: $size,
                    key: 'price_per_hour',
                    resource: $resource,
                ),
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: $this->requiredNonNegativeDecimalString(
                    data: $size,
                    key: 'price_per_month',
                    resource: $resource,
                ),
                currencyCode: null,
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }

    /**
     * Resize-plan responses expose byte fields while single-plan responses
     * expose memory directly in MiB.
     *
     * @param  array<string, mixed>  $size
     */
    private function sizeMemoryMiB(
        array $size,
        string $resource,
    ): int {
        if (
            array_key_exists('memory_in_bytes', $size)
            && $size['memory_in_bytes'] !== null
            && $size['memory_in_bytes'] !== ''
        ) {
            return $this->bytesToMiB(
                $this->requiredFlexibleNonNegativeInt(
                    data: $size,
                    key: 'memory_in_bytes',
                    resource: $resource,
                ),
                "{$resource}.memory_in_bytes",
            );
        }

        return $this->requiredFlexibleNonNegativeInt(
            data: $size,
            key: 'memory',
            resource: $resource,
        );
    }

    /**
     * Resize-plan responses expose byte fields while single-plan responses
     * expose disk directly in GiB.
     *
     * @param  array<string, mixed>  $size
     */
    private function sizeDiskGiB(
        array $size,
        string $resource,
    ): int {
        if (
            array_key_exists('disk_in_bytes', $size)
            && $size['disk_in_bytes'] !== null
            && $size['disk_in_bytes'] !== ''
        ) {
            return $this->bytesToGiB(
                $this->requiredFlexibleNonNegativeInt(
                    data: $size,
                    key: 'disk_in_bytes',
                    resource: $resource,
                ),
                "{$resource}.disk_in_bytes",
            );
        }

        return $this->requiredFlexibleNonNegativeInt(
            data: $size,
            key: 'disk',
            resource: $resource,
        );
    }

    /**
     * String references are valid for fields such as flavor and image,
     * but they do not contain nested metadata.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function optionalNestedObject(
        array $data,
        string $key,
        string $resource,
    ): ?array {
        if (
            ! array_key_exists($key, $data)
            || $data[$key] === null
            || is_string($data[$key])
        ) {
            return null;
        }

        if (
            ! is_array($data[$key])
            || array_is_list($data[$key])
        ) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s must be an object, string, or null.',
                    $resource,
                ),
            );
        }

        /** @var array<string, mixed> $object */
        $object = $data[$key];

        return $object;
    }

    /**
     * OpenAPI describes some server flavor values as numbers rather than
     * strict integers, while xDeploy requires whole resource units.
     *
     * @param  array<string, mixed>  $data
     */
    private function optionalNonNegativeInt(
        array $data,
        string $key,
        string $resource,
    ): ?int {
        if (
            ! array_key_exists($key, $data)
            || $data[$key] === null
            || $data[$key] === ''
        ) {
            return null;
        }

        $value = $data[$key];

        if (is_int($value)) {
            if ($value < 0) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s field [%s] must be a non-negative integer.',
                        $resource,
                        $key,
                    ),
                );
            }

            return $value;
        }

        if (
            is_float($value)
            && is_finite($value)
            && $value >= 0
            && floor($value) === $value
            && $value <= PHP_INT_MAX
        ) {
            return (int) $value;
        }

        if (is_string($value)) {
            $value = trim(
                $value,
            );

            $normalized = filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 0,
                    ],
                ],
            );

            if ($normalized !== false) {
                return $normalized;
            }
        }

        throw new CloudUnexpectedResponseException(
            sprintf(
                'ArvanCloud %s field [%s] must be a non-negative integer.',
                $resource,
                $key,
            ),
        );
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

        $username ??= $this->nestedOptionalString(
            data: $server,
            objectKey: 'image',
            valueKey: 'username',
            resource: 'server image',
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
     * Accept direct objects, direct lists, and optional data envelopes.
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function resourceItems(
        array $payload,
        string $resource,
    ): array {
        $candidate = array_key_exists('data', $payload)
            ? $payload['data']
            : $payload;

        if (! is_array($candidate)) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s response has an invalid data envelope.',
                    $resource,
                ),
            );
        }

        if ($candidate === []) {
            return [];
        }

        if (! array_is_list($candidate)) {
            /** @var array<string, mixed> $candidate */
            return [
                $candidate,
            ];
        }

        foreach ($candidate as $item) {
            if (
                ! is_array($item)
                || array_is_list($item)
            ) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s response contains an invalid item.',
                        $resource,
                    ),
                );
            }
        }

        /** @var list<array<string, mixed>> $candidate */
        return $candidate;
    }

    /**
     * Accept direct objects, one-item lists, and optional data envelopes.
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resourceObject(
        array $payload,
        string $resource,
    ): array {
        $candidate = array_key_exists('data', $payload)
            ? $payload['data']
            : $payload;

        if (! is_array($candidate)) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s response has an invalid data envelope.',
                    $resource,
                ),
            );
        }

        if (! array_is_list($candidate)) {
            /** @var array<string, mixed> $candidate */
            return $candidate;
        }

        if (
            count($candidate) === 1
            && is_array($candidate[0])
            && ! array_is_list($candidate[0])
        ) {
            /** @var array<string, mixed> $object */
            $object = $candidate[0];

            return $object;
        }

        throw new CloudUnexpectedResponseException(
            sprintf(
                'ArvanCloud %s response has an invalid data envelope.',
                $resource,
            ),
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
    private function requiredFlexibleNonNegativeInt(
        array $data,
        string $key,
        string $resource,
    ): int {
        $value = $this->optionalNonNegativeInt(
            data: $data,
            key: $key,
            resource: $resource,
        );

        if ($value === null) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a non-negative integer.',
                    $resource,
                    $key,
                ),
            );
        }

        return $value;
    }

    /**
     * Prices are preserved as decimal strings to avoid float arithmetic.
     *
     * @param  array<string, mixed>  $data
     */
    private function requiredNonNegativeDecimalString(
        array $data,
        string $key,
        string $resource,
    ): string {
        if (! array_key_exists($key, $data)) {
            throw new CloudUnexpectedResponseException(
                sprintf(
                    'ArvanCloud %s field [%s] must be a non-negative number.',
                    $resource,
                    $key,
                ),
            );
        }

        $value = $data[$key];

        if (is_int($value) && $value >= 0) {
            return (string) $value;
        }

        if (
            is_float($value)
            && is_finite($value)
            && $value >= 0
        ) {
            $normalized = rtrim(
                rtrim(
                    sprintf('%.14F', $value),
                    '0',
                ),
                '.',
            );

            return $normalized === '-0'
                ? '0'
                : $normalized;
        }

        if (is_string($value)) {
            $value = trim(
                $value,
            );

            if (
                preg_match(
                    '/\A\d+(?:\.\d+)?\z/',
                    $value,
                ) === 1
            ) {
                return $value;
            }
        }

        throw new CloudUnexpectedResponseException(
            sprintf(
                'ArvanCloud %s field [%s] must be a non-negative number.',
                $resource,
                $key,
            ),
        );
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

    /**
     * @param  array<array-key, mixed>  $payload
     * @return list<CloudPortData>
     */
    public function mapPorts(
        array $payload,
        string $serverId,
    ): array {
        $ports = $this->portItems(
            $payload,
        );

        $mappedPorts = [];

        foreach ($ports as $port) {
            $portServerId = $this->requiredString(
                data: $port,
                key: 'instance_id',
                resource: 'port',
            );

            if ($portServerId !== $serverId) {
                continue;
            }

            $mappedPorts[] = new CloudPortData(
                id: $this->requiredString(
                    data: $port,
                    key: 'id',
                    resource: 'port',
                ),

                serverId: $portServerId,

                ips: $this->requiredStringList(
                    data: $port,
                    key: 'ips',
                    resource: 'port',
                ),

                macAddress: $this->requiredString(
                    data: $port,
                    key: 'mac_address',
                    resource: 'port',
                ),

                networkId: $this->requiredString(
                    data: $port,
                    key: 'network_id',
                    resource: 'port',
                ),

                portSecurityEnabled: $this->requiredBool(
                    data: $port,
                    key: 'port_security_enabled',
                    resource: 'port',
                ),

                securityGroupIds: $this->requiredStringList(
                    data: $port,
                    key: 'security_group_ids',
                    resource: 'port',
                ),

                status: $this->requiredString(
                    data: $port,
                    key: 'status',
                    resource: 'port',
                ),
            );
        }

        return $mappedPorts;
    }

    /**
     * API Documentation پاسخ Ports را گاهی به‌صورت Object نمایش
     * می‌دهد، درحالی‌که Endpoint به‌عنوان List معرفی شده است.
     *
     * @param  array<array-key, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function portItems(
        array $payload,
    ): array {
        $candidate = array_key_exists(
            'data',
            $payload,
        )
            ? $payload['data']
            : $payload;

        if (! is_array($candidate)) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud ports response has an invalid data envelope.',
            );
        }

        if ($candidate === []) {
            return [];
        }

        if (! array_is_list($candidate)) {
            if (! array_key_exists('id', $candidate)) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud ports response has an invalid data envelope.',
                );
            }

            /** @var array<string, mixed> $candidate */
            return [
                $candidate,
            ];
        }

        foreach ($candidate as $port) {
            if (
                ! is_array($port)
                || array_is_list($port)
            ) {
                throw new CloudUnexpectedResponseException(
                    'ArvanCloud ports response contains an invalid item.',
                );
            }
        }

        /** @var list<array<string, mixed>> $candidate */
        return $candidate;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function requiredStringList(
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
                    'ArvanCloud %s field [%s] must be a list of strings.',
                    $resource,
                    $key,
                ),
            );
        }

        $values = [];

        foreach ($data[$key] as $value) {
            if (
                ! is_string($value)
                || trim($value) === ''
            ) {
                throw new CloudUnexpectedResponseException(
                    sprintf(
                        'ArvanCloud %s field [%s] contains an invalid value.',
                        $resource,
                        $key,
                    ),
                );
            }

            $values[] = trim(
                $value,
            );
        }

        return $values;
    }
}
