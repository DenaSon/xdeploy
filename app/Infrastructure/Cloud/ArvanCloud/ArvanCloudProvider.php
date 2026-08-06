<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudPortData;
use App\Domain\Cloud\DTOs\CloudQuotaData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CloudSshKeyData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\ArvanCloud\Mappers\ArvanCloudResponseMapper;

final readonly class ArvanCloudProvider implements CloudProviderInterface, CloudServerLifecycleInterface, CloudServerNetworkingInterface, CloudServerProvisionerInterface
{
    private const string RESOURCE_REGIONS = 'regions';

    private const string RESOURCE_SIZES = 'sizes';

    private const string RESOURCE_IMAGES = 'images';

    private const string RESOURCE_NETWORKS = 'networks';

    private const string RESOURCE_SECURITIES = 'securities';

    private const string RESOURCE_QUOTA = 'quota';

    private const string RESOURCE_SSH_KEYS = 'ssh-keys';

    private const string RESOURCE_SERVERS = 'servers';

    private const string RESOURCE_PORTS = 'ports';

    private const string ACTION_POWER_ON = 'power-on';

    private const string ACTION_POWER_OFF = 'power-off';

    private const string ACTION_REBOOT = 'reboot';

    private const string ACTION_ADD_PUBLIC_IP = 'add-public-ip';

    private const string ACTIONS = 'actions';

    public function __construct(
        private ArvanCloudClient $client,
        private ArvanCloudResponseMapper $mapper,
        private string $createType = 'cinder',
        private string $defaultUsername = 'ubuntu',
    ) {}

    /**
     * @return list<CloudRegionData>
     */
    public function listRegions(): array
    {
        return $this->mapper->mapRegions(
            $this->client->get(
                self::RESOURCE_REGIONS,
            ),
        );
    }

    /**
     * @return list<CloudSizeData>
     */
    public function listSizes(
        string $region,
    ): array {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $payload = $this->client->get(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_SIZES,
            ),
        );

        return $this->mapper->mapSizes(
            payload: $payload,
            regionId: $regionId,
        );
    }

    /**
     * @return list<CloudImageData>
     */
    public function listImages(
        string $region,
    ): array {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $payload = $this->client->get(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_IMAGES,
            ),
            [
                'type' => 'distributions',
            ],
        );

        return $this->mapper->mapImages(
            payload: $payload,
            regionId: $regionId,
        );
    }

    /**
     * @return list<CloudNetworkData>
     */
    public function listNetworks(
        string $region,
    ): array {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $payload = $this->client->get(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_NETWORKS,
            ),
        );

        return $this->mapper->mapNetworks(
            payload: $payload,
            regionId: $regionId,
        );
    }

    /**
     * @return list<CloudSecurityGroupData>
     */
    public function listSecurityGroups(
        string $region,
    ): array {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $payload = $this->client->get(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_SECURITIES,
            ),
        );

        return $this->mapper->mapSecurityGroups(
            payload: $payload,
            regionId: $regionId,
        );
    }

    public function getQuota(
        string $region,
    ): CloudQuotaData {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $payload = $this->client->get(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_QUOTA,
            ),
        );

        return $this->mapper->mapQuota(
            payload: $payload,
            regionId: $regionId,
        );
    }

    /**
     * @return list<CloudSshKeyData>
     */
    public function listSshKeys(
        string $region,
    ): array {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $payload = $this->client->get(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_SSH_KEYS,
            ),
        );

        return $this->mapper->mapSshKeys(
            payload: $payload,
            regionId: $regionId,
        );
    }

    public function createServer(
        CreateCloudServerData $data,
    ): CreatedCloudServerData {
        $regionId = $this->normalizeRegion(
            $data->regionId,
        );

        $serverName = $this->normalizeServerName(
            $data->name,
        );

        $payload = $this->createServerPayload(
            data: $data,
            serverName: $serverName,
        );

        $response = $this->client->post(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_SERVERS,
            ),
            $payload,
        );

        $createdServer = $this->mapper->mapCreatedServer(
            payload: $response,
            regionId: $regionId,
            defaultUsername: $this->normalizedDefaultUsername(),
            requestedName: $serverName,
        );

        if (
            ! $data->usesSshKey()
            && ! $createdServer->hasGeneratedPassword()
        ) {
            throw new CloudUnexpectedResponseException(
                'ArvanCloud create response did not contain a generated password.',
            );
        }

        return $createdServer;
    }

    public function findServer(
        string $region,
        string $serverId,
    ): CloudServerData {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        /*
         * Server details are fetched directly. This avoids loading the
         * complete server list during provisioning and resize polling.
         */
        $payload = $this->client->get(
            $this->serverEndpoint(
                regionId: $regionId,
                serverId: $providerServerId,
            ),
        );

        return $this->mapper->mapServer(
            payload: $payload,
            regionId: $regionId,
            serverId: $providerServerId,
            defaultUsername: $this->normalizedDefaultUsername(),
        );
    }

    public function powerOn(
        string $region,
        string $serverId,
    ): void {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        $this->client->post(
            $this->serverActionEndpoint(
                regionId: $regionId,
                serverId: $providerServerId,
                action: self::ACTION_POWER_ON,
            ),
        );
    }

    public function powerOff(
        string $region,
        string $serverId,
    ): void {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        $this->client->post(
            $this->serverActionEndpoint(
                regionId: $regionId,
                serverId: $providerServerId,
                action: self::ACTION_POWER_OFF,
            ),
        );
    }

    public function reboot(
        string $region,
        string $serverId,
    ): void {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        $this->client->post(
            $this->serverActionEndpoint(
                regionId: $regionId,
                serverId: $providerServerId,
                action: self::ACTION_REBOOT,
            ),
        );
    }

    public function deleteServer(
        string $region,
        string $serverId,
    ): void {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        $this->client->delete(
            $this->serverEndpoint(
                regionId: $regionId,
                serverId: $providerServerId,
            ),
        );
    }

    /**
     * @return list<CloudServerActionData>
     */
    public function getAvailableActions(
        string $region,
        string $serverId,
    ): array {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        $payload = $this->client->get(
            $this->serverActionEndpoint(
                regionId: $regionId,
                serverId: $providerServerId,
                action: self::ACTIONS,
            ),
        );

        return $this->mapper->mapServerActions(
            $payload,
        );
    }

    /**
     * @return list<CloudPortData>
     */
    public function listServerPorts(
        string $region,
        string $serverId,
    ): array {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        $payload = $this->client->get(
            $this->regionEndpoint(
                regionId: $regionId,
                resource: self::RESOURCE_PORTS,
            ),
        );

        return $this->mapper->mapPorts(
            payload: $payload,
            serverId: $providerServerId,
        );
    }

    /**
     * @param  list<string>  $securityGroupIds
     */
    public function addPublicIp(
        string $region,
        string $serverId,
        CloudIpVersion $version = CloudIpVersion::IPv4,
        array $securityGroupIds = [],
    ): void {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        $normalizedSecurityGroupIds =
            $this->normalizeOptionalResourceIds(
                ids: $securityGroupIds,
                resource: 'security group',
            );

        $payload = [
            'type' => $version->value,
        ];

        if ($normalizedSecurityGroupIds !== []) {
            $payload['security_groups'] =
                $normalizedSecurityGroupIds;
        }

        $this->client->post(
            $this->serverActionEndpoint(
                regionId: $regionId,
                serverId: $providerServerId,
                action: self::ACTION_ADD_PUBLIC_IP,
            ),
            $payload,
        );
    }

    public function deletePort(
        string $region,
        string $portId,
    ): void {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerPortId = $this->normalizeResourceId(
            id: $portId,
            resource: 'port',
        );

        $this->client->delete(
            $this->portEndpoint(
                regionId: $regionId,
                portId: $providerPortId,
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function createServerPayload(
        CreateCloudServerData $data,
        string $serverName,
    ): array {
        $securityGroupIds = $this->normalizeSecurityGroupIds(
            $data->securityGroupIds,
        );

        return [
            'name' => $serverName,

            'network_id' => $this->normalizeResourceId(
                id: $data->networkId,
                resource: 'network',
            ),

            'flavor_id' => $this->normalizeResourceId(
                id: $data->sizeId,
                resource: 'size',
            ),

            'image_id' => $this->normalizeResourceId(
                id: $data->imageId,
                resource: 'image',
            ),

            /*
             * ArvanCloud expects the security group UUID inside
             * security_groups[].name.
             */
            'security_groups' => array_map(
                static fn (string $id): array => [
                    'name' => $id,
                ],
                $securityGroupIds,
            ),

            'ssh_key' => $data->usesSshKey(),

            'key_name' => $data->usesSshKey()
                ? trim((string) $data->sshKeyName)
                : null,

            'count' => 1,

            'create_type' => $this->normalizeCreateType(),

            'disk_size' => $this->normalizeDiskSize(
                $data->diskGiB,
            ),

            'init_script' => $data->initializationScript,

            'ha_enabled' => $data->highAvailability,
        ];
    }

    private function regionEndpoint(
        string $regionId,
        string $resource,
    ): string {
        return sprintf(
            'regions/%s/%s',
            rawurlencode(
                $regionId,
            ),
            rawurlencode(
                $resource,
            ),
        );
    }

    private function serverEndpoint(
        string $regionId,
        string $serverId,
    ): string {
        return sprintf(
            'regions/%s/servers/%s',
            rawurlencode(
                $regionId,
            ),
            rawurlencode(
                $serverId,
            ),
        );
    }

    private function serverActionEndpoint(
        string $regionId,
        string $serverId,
        string $action,
    ): string {
        return sprintf(
            '%s/%s',
            $this->serverEndpoint(
                regionId: $regionId,
                serverId: $serverId,
            ),
            rawurlencode(
                $action,
            ),
        );
    }

    private function portEndpoint(
        string $regionId,
        string $portId,
    ): string {
        return sprintf(
            'regions/%s/ports/%s',
            rawurlencode(
                $regionId,
            ),
            rawurlencode(
                $portId,
            ),
        );
    }

    private function normalizeRegion(
        string $region,
    ): string {
        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $region,
            ) === 1
        ) {
            throw new CloudValidationException(
                'Cloud region identifier is invalid.',
            );
        }

        $region = trim(
            $region,
        );

        if ($region === '') {
            throw new CloudValidationException(
                'Cloud region identifier cannot be empty.',
            );
        }

        if (
            preg_match(
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
        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $id,
            ) === 1
        ) {
            throw new CloudValidationException(
                sprintf(
                    'Cloud %s identifier is invalid.',
                    $resource,
                ),
            );
        }

        $id = trim(
            $id,
        );

        if ($id === '') {
            throw new CloudValidationException(
                sprintf(
                    'Cloud %s identifier cannot be empty.',
                    $resource,
                ),
            );
        }

        if (
            preg_match(
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

    private function normalizeServerName(
        string $name,
    ): string {
        $name = trim(
            $name,
        );

        if (
            $name === ''
            || mb_strlen(
                $name,
            ) > 255
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $name,
            ) === 1
        ) {
            throw new CloudValidationException(
                'Cloud server name is invalid.',
            );
        }

        return $name;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function normalizeSecurityGroupIds(
        array $ids,
    ): array {
        $normalized = $this->normalizeOptionalResourceIds(
            ids: $ids,
            resource: 'security group',
        );

        if ($normalized === []) {
            throw new CloudValidationException(
                'At least one cloud security group is required.',
            );
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function normalizeOptionalResourceIds(
        array $ids,
        string $resource,
    ): array {
        $normalized = [];

        foreach ($ids as $id) {
            if (! is_string($id)) {
                throw new CloudValidationException(
                    sprintf(
                        'Cloud %s identifier must be a string.',
                        $resource,
                    ),
                );
            }

            $normalized[] = $this->normalizeResourceId(
                id: $id,
                resource: $resource,
            );
        }

        return array_values(
            array_unique(
                $normalized,
            ),
        );
    }

    private function normalizeDiskSize(
        int $diskGiB,
    ): int {
        if ($diskGiB < 1) {
            throw new CloudValidationException(
                'Cloud server disk size must be greater than zero.',
            );
        }

        return $diskGiB;
    }

    private function normalizeCreateType(): string
    {
        return $this->normalizeResourceId(
            id: $this->createType,
            resource: 'create type',
        );
    }

    private function normalizedDefaultUsername(): string
    {
        $username = trim(
            $this->defaultUsername,
        );

        if (
            $username === ''
            || preg_match(
                '/\A[a-z_][a-z0-9_-]*[$]?\z/i',
                $username,
            ) !== 1
        ) {
            throw new CloudValidationException(
                'Cloud image default username is invalid.',
            );
        }

        return $username;
    }
}
