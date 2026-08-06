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
    public function __construct(
        private ArvanCloudClient $client,
        private ArvanCloudResponseMapper $mapper,
        private string $createType = 'cinder',
        private string $defaultUsername = 'ubuntu',
    ) {}

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

        return $this->mapper->mapPorts(
            payload: $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'ports',
                ),
            ),
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
                region: $regionId,
                serverId: $providerServerId,
                action: 'add-public-ip',
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
                region: $regionId,
                portId: $providerPortId,
            ),
        );
    }

    /**
     * @return list<CloudRegionData>
     */
    public function listRegions(): array
    {
        return $this->mapper->mapRegions(
            $this->client->get('regions'),
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

        return $this->mapper->mapSizes(
            $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'sizes',
                ),
            ),
            $regionId,
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

        return $this->mapper->mapImages(
            $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'images',
                ),
                [
                    'type' => 'distributions',
                ],
            ),
            $regionId,
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

        return $this->mapper->mapNetworks(
            $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'networks',
                ),
            ),
            $regionId,
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

        return $this->mapper->mapSecurityGroups(
            $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'securities',
                ),
            ),
            $regionId,
        );
    }

    public function getQuota(
        string $region,
    ): CloudQuotaData {
        $regionId = $this->normalizeRegion(
            $region,
        );

        return $this->mapper->mapQuota(
            $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'quota',
                ),
            ),
            $regionId,
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

        return $this->mapper->mapSshKeys(
            $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'ssh-keys',
                ),
            ),
            $regionId,
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

        $payload = [
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

            'security_groups' => array_map(
                static fn (string $id): array => [
                    'name' => $id,
                ],
                $this->normalizeSecurityGroupIds(
                    $data->securityGroupIds,
                ),
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

        $response = $this->client->post(
            $this->regionEndpoint(
                region: $regionId,
                resource: 'servers',
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

        return $this->mapper->mapServer(
            payload: $this->client->get(
                $this->regionEndpoint(
                    region: $regionId,
                    resource: 'servers',
                ),
            ),
            regionId: $regionId,
            serverId: $providerServerId,
            defaultUsername: $this->normalizedDefaultUsername(),
        );
    }

    public function powerOn(
        string $region,
        string $serverId,
    ): void {
        $this->client->post(
            $this->serverActionEndpoint(
                region: $region,
                serverId: $serverId,
                action: 'power-on',
            ),
        );
    }

    public function powerOff(
        string $region,
        string $serverId,
    ): void {
        $this->client->post(
            $this->serverActionEndpoint(
                region: $region,
                serverId: $serverId,
                action: 'power-off',
            ),
        );
    }

    public function reboot(
        string $region,
        string $serverId,
    ): void {
        $this->client->post(
            $this->serverActionEndpoint(
                region: $region,
                serverId: $serverId,
                action: 'reboot',
            ),
        );
    }

    public function deleteServer(
        string $region,
        string $serverId,
    ): void {
        $this->client->delete(
            $this->serverEndpoint(
                region: $region,
                serverId: $serverId,
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
        return $this->mapper->mapServerActions(
            $this->client->get(
                $this->serverActionEndpoint(
                    region: $region,
                    serverId: $serverId,
                    action: 'actions',
                ),
            ),
        );
    }

    private function regionEndpoint(
        string $region,
        string $resource,
    ): string {
        return sprintf(
            'regions/%s/%s',
            rawurlencode($region),
            $resource,
        );
    }

    private function serverEndpoint(
        string $region,
        string $serverId,
    ): string {
        $regionId = $this->normalizeRegion(
            $region,
        );

        $providerServerId = $this->normalizeResourceId(
            id: $serverId,
            resource: 'server',
        );

        return sprintf(
            'regions/%s/servers/%s',
            rawurlencode($regionId),
            rawurlencode($providerServerId),
        );
    }

    private function serverActionEndpoint(
        string $region,
        string $serverId,
        string $action,
    ): string {
        return sprintf(
            '%s/%s',
            $this->serverEndpoint(
                region: $region,
                serverId: $serverId,
            ),
            $action,
        );
    }

    private function normalizeRegion(
        string $region,
    ): string {
        if (
            $region === ''
            || preg_match(
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
            $id === ''
            || preg_match(
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

    private function normalizeServerName(
        string $name,
    ): string {
        $name = trim(
            $name,
        );

        if (
            $name === ''
            || mb_strlen($name) > 255
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
        $normalized = [];

        foreach ($ids as $id) {
            if (! is_string($id)) {
                throw new CloudValidationException(
                    'Cloud security group identifier must be a string.',
                );
            }

            $normalized[] = $this->normalizeResourceId(
                id: $id,
                resource: 'security group',
            );
        }

        $normalized = array_values(
            array_unique($normalized),
        );

        if ($normalized === []) {
            throw new CloudValidationException(
                'At least one cloud security group is required.',
            );
        }

        return $normalized;
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

    private function portEndpoint(
        string $region,
        string $portId,
    ): string {
        return sprintf(
            'regions/%s/ports/%s',
            rawurlencode($region),
            rawurlencode($portId),
        );
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
            array_unique($normalized),
        );
    }
}
