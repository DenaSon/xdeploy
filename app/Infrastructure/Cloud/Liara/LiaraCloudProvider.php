<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Liara;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudServerBootstrapCredentialRotationInterface;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\Liara\Mappers\LiaraCloudResponseMapper;

final readonly class LiaraCloudProvider implements CloudProviderInterface, CloudServerBootstrapCredentialRotationInterface, CloudServerCredentialManagerInterface, CloudServerInventoryInterface, CloudServerLifecycleInterface, CloudServerProvisionerInterface, CloudServerResizeCatalogInterface
{
    private const string RESOURCE_PLANS = 'plans';

    private const string RESOURCE_OPERATING_SYSTEMS = 'oss';

    private const string RESOURCE_SERVERS = 'vm';

    private const string ACTION_POWER_ON = 'power-on';

    private const string ACTION_POWER_OFF = 'power-off';

    private const string ACTION_REBOOT = 'reboot';

    public function __construct(
        private LiaraCloudClient $client,
        private LiaraCloudResponseMapper $mapper,
    ) {}

    /**
     * @return list<CloudRegionData>
     */
    public function listRegions(): array
    {
        return $this->mapper->mapRegions(
            $this->client->get(self::RESOURCE_PLANS),
        );
    }

    /**
     * @return list<CloudSizeData>
     */
    public function listSizes(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapSizes(
            response: $this->client->get(self::RESOURCE_PLANS),
            region: $regionId,
        );
    }

    /**
     * @return list<CloudImageData>
     */
    public function listImages(string $region): array
    {
        $regionId = $this->normalizeRegion($region);
        $response = $this->client->get(self::RESOURCE_OPERATING_SYSTEMS);

        return $this->mapper->mapImages(
            response: $this->normalizeOperatingSystemCatalog($response),
            region: $regionId,
        );
    }

    public function createServer(
        CreateCloudServerData $data,
    ): CreatedCloudServerData {
        $regionId = $this->normalizeRegion($data->regionId);
        $serverName = $this->normalizeServerName($data->name);
        $sizeId = $this->normalizeCatalogId($data->sizeId, 'plan');
        $imageId = $this->normalizeCatalogId($data->imageId, 'operating system');

        if ($data->hasAnyProvisioningInfrastructure()) {
            throw new CloudValidationException(
                'Liara manages VM networking internally and does not accept Coreflare network or security-group identifiers.',
            );
        }

        if ($data->usesSshKey()) {
            throw new CloudValidationException(
                'Liara provisioning through Coreflare currently supports generated root passwords only.',
            );
        }

        if (trim($data->initializationScript) !== '') {
            throw new CloudValidationException(
                'Liara VM initialization scripts are not supported by the current adapter.',
            );
        }

        if ($data->highAvailability) {
            throw new CloudValidationException(
                'Liara VM high availability is not supported by the current adapter.',
            );
        }

        $size = $this->findSize(
            region: $regionId,
            sizeId: $sizeId,
        );

        if ($data->diskGiB !== $size->diskGiB) {
            throw new CloudValidationException(
                sprintf(
                    'Liara plan [%s] currently requires its default [%d] GiB storage entitlement during provisioning.',
                    $size->id,
                    $size->diskGiB,
                ),
            );
        }

        $response = $this->client->post(
            self::RESOURCE_SERVERS,
            [
                'name' => $serverName,
                'OS' => $imageId,
                'plan' => $sizeId,
                'config' => [
                    'SSHKeys' => [],
                ],
            ],
        );

        return $this->mapper->mapCreatedServer(
            response: $response,
            requestedName: $serverName,
            region: $regionId,
        );
    }

    public function findServer(
        string $region,
        string $serverId,
    ): CloudServerData {
        $regionId = $this->normalizeRegion($region);
        $providerServerId = $this->normalizeServerId($serverId);

        return $this->mapper->mapServer(
            response: $this->client->get(
                $this->serverEndpoint($providerServerId),
            ),
            region: $regionId,
        );
    }

    /**
     * @return list<CloudServerData>
     */
    public function listServers(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapServerInventory(
            response: $this->client->get(self::RESOURCE_SERVERS),
            region: $regionId,
        );
    }

    public function powerOn(
        string $region,
        string $serverId,
    ): void {
        $this->assertRegion($region);
        $providerServerId = $this->normalizeServerId($serverId);

        $this->client->patch(
            $this->powerEndpoint($providerServerId),
            [
                'action' => 'start',
            ],
        );
    }

    public function powerOff(
        string $region,
        string $serverId,
    ): void {
        $this->assertRegion($region);
        $providerServerId = $this->normalizeServerId($serverId);

        $this->client->patch(
            $this->powerEndpoint($providerServerId),
            [
                'action' => 'stop',
            ],
        );
    }

    public function reboot(
        string $region,
        string $serverId,
    ): void {
        $this->assertRegion($region);
        $providerServerId = $this->normalizeServerId($serverId);

        $this->client->patch(
            $this->powerEndpoint($providerServerId),
            [
                'action' => 'reboot',
            ],
        );
    }

    public function deleteServer(
        string $region,
        string $serverId,
    ): void {
        $this->assertRegion($region);
        $providerServerId = $this->normalizeServerId($serverId);

        $this->client->delete(
            $this->serverEndpoint($providerServerId),
        );
    }

    /**
     * @return list<CloudServerActionData>
     */
    public function getAvailableActions(
        string $region,
        string $serverId,
    ): array {
        $server = $this->findServer(
            region: $region,
            serverId: $serverId,
        );

        if ($server->isRunning()) {
            return [
                new CloudServerActionData(self::ACTION_POWER_OFF),
                new CloudServerActionData(self::ACTION_REBOOT),
            ];
        }

        if ($server->isStopped()) {
            return [
                new CloudServerActionData(self::ACTION_POWER_ON),
            ];
        }

        return [];
    }

    public function resetRootPassword(
        string $region,
        string $serverId,
    ): CloudRootPasswordResetData {
        $this->assertRegion($region);
        $providerServerId = $this->normalizeServerId($serverId);

        return $this->mapper->mapRootPasswordReset(
            $this->client->post(
                sprintf('vm/reset-password/%s', $providerServerId),
            ),
        );
    }

    /**
     * Liara currently exposes resize targets through the same plan catalog.
     * Actual resize execution is intentionally a separate capability.
     *
     * @return list<CloudSizeData>
     */
    public function listServerResizePlans(
        string $region,
        string $serverId,
    ): array {
        $this->normalizeServerId($serverId);

        return $this->listSizes($region);
    }

    public function findSize(
        string $region,
        string $sizeId,
    ): CloudSizeData {
        $sizeId = $this->normalizeCatalogId($sizeId, 'plan');

        foreach ($this->listSizes($region) as $size) {
            if ($size->id === $sizeId) {
                return $size;
            }
        }

        throw new CloudResourceNotFoundException(
            sprintf('Liara plan [%s] was not found.', $sizeId),
        );
    }

    public function calculateSize(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudSizeData {
        $size = $this->findSize(
            region: $region,
            sizeId: $sizeId,
        );

        if ($diskGiB !== $size->diskGiB) {
            throw new CloudValidationException(
                'Liara custom disk sizing is not supported by the current pricing adapter.',
            );
        }

        return $size;
    }

    public function calculateDiskPrice(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudDiskPriceData {
        $size = $this->findSize(
            region: $region,
            sizeId: $sizeId,
        );

        if ($diskGiB !== $size->diskGiB) {
            throw new CloudValidationException(
                'Liara custom disk pricing is not exposed by the current adapter.',
            );
        }

        return new CloudDiskPriceData(
            diskGiB: $diskGiB,
            hourlyPrice: new CloudPriceData(
                amount: '0',
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Hourly,
            ),
            monthlyPrice: new CloudPriceData(
                amount: '0',
                currencyCode: 'IRR',
                billingPeriod: CloudBillingPeriod::Monthly,
            ),
        );
    }

    /**
     * Liara exposes regular operating systems at the top level while
     * one-click application images are nested under [one-click-apps].
     * Coreflare keeps the mapper provider-shape-neutral by flattening that
     * Liara-specific grouping at the provider boundary.
     *
     * @param  array<array-key, mixed>  $response
     * @return array<array-key, mixed>
     */
    private function normalizeOperatingSystemCatalog(array $response): array
    {
        if (! array_key_exists('one-click-apps', $response)) {
            return $response;
        }

        $oneClickApps = $response['one-click-apps'];

        if (! is_array($oneClickApps)) {
            throw new CloudUnexpectedResponseException(
                'Liara one-click application catalog payload is invalid.',
            );
        }

        unset($response['one-click-apps']);

        foreach ($oneClickApps as $application => $versions) {
            if (
                ! is_string($application)
                || trim($application) === ''
                || array_key_exists($application, $response)
            ) {
                throw new CloudUnexpectedResponseException(
                    'Liara one-click application catalog contains an invalid entry.',
                );
            }

            $response[$application] = $versions;
        }

        return $response;
    }

    private function assertRegion(string $region): void
    {
        $this->normalizeRegion($region);
    }

    private function normalizeRegion(string $region): string
    {
        $region = strtolower(trim($region));

        if (
            $region === ''
            || preg_match('/\A[a-z0-9][a-z0-9-]{0,62}\z/', $region) !== 1
        ) {
            throw new CloudValidationException(
                'Liara region identifier is invalid.',
            );
        }

        return $region;
    }

    private function normalizeServerId(string $serverId): string
    {
        $serverId = strtolower(trim($serverId));

        if (preg_match('/\A[a-f0-9]{24}\z/', $serverId) !== 1) {
            throw new CloudValidationException(
                'Liara VM identifier is invalid.',
            );
        }

        return $serverId;
    }

    private function normalizeCatalogId(
        string $id,
        string $resource,
    ): string {
        $id = strtolower(trim($id));

        if (
            $id === ''
            || preg_match('/\A[a-z0-9][a-z0-9._-]{0,127}\z/', $id) !== 1
        ) {
            throw new CloudValidationException(
                sprintf('Liara %s identifier is invalid.', $resource),
            );
        }

        return $id;
    }

    private function normalizeServerName(string $name): string
    {
        $name = trim($name);

        if (
            strlen($name) < 4
            || strlen($name) > 19
            || preg_match('/\A[a-z][a-z0-9-]+\z/', $name) !== 1
        ) {
            throw new CloudValidationException(
                'Liara VM name must be 4 to 19 characters, start with a lowercase letter, and contain only lowercase letters, numbers, or hyphens.',
            );
        }

        return $name;
    }

    private function serverEndpoint(string $serverId): string
    {
        return sprintf('vm/%s', $serverId);
    }

    private function powerEndpoint(string $serverId): string
    {
        return sprintf('vm/power/%s', $serverId);
    }
}
