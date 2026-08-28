<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ParsPack;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\Contracts\CloudPurchaseCatalogSourceInterface;
use App\Domain\Cloud\Contracts\CloudPurchasePricingSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerBootstrapCredentialRotationInterface;
use App\Domain\Cloud\Contracts\CloudServerBootstrapCredentialSourceInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Contracts\CloudServerProvisionerInterface;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudPriceData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Domain\Cloud\DTOs\CloudServerBootstrapCredentialData;
use App\Domain\Cloud\DTOs\CloudServerData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\DTOs\CreateCloudServerData;
use App\Domain\Cloud\DTOs\CreatedCloudServerData;
use App\Domain\Cloud\Enums\CloudBillingPeriod;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Infrastructure\Cloud\ParsPack\Mappers\ParsPackCloudResponseMapper;
use SensitiveParameter;

final readonly class ParsPackCloudProvider implements
    CloudProviderInterface,
    CloudPurchaseCatalogSourceInterface,
    CloudPurchasePricingSourceInterface,
    CloudServerBootstrapCredentialRotationInterface,
    CloudServerBootstrapCredentialSourceInterface,
    CloudServerInventoryInterface,
    CloudServerLifecycleInterface,
    CloudServerProvisionerInterface,
    CloudServerResizeCatalogInterface
{
    private const string RESOURCE_REGIONS = 'regions';
    private const string RESOURCE_SIZES = 'sizes';
    private const string RESOURCE_IMAGES = 'images';
    private const string RESOURCE_SERVERS = 'vms';

    private const string ACTION_POWER_ON = 'power-on';
    private const string ACTION_POWER_OFF = 'power-off';
    private const string ACTION_REBOOT = 'reboot';

    private readonly int $bootstrapSshKeyId;
    private readonly string $bootstrapPrivateKey;
    private readonly int $fundingOverheadPercent;

    public function __construct(
        private ParsPackCloudClient $client,
        private ParsPackCloudResponseMapper $mapper,
        int $bootstrapSshKeyId,
        #[SensitiveParameter]
        string $bootstrapPrivateKey,
        int $fundingOverheadPercent = 0,
    ) {
        if ($bootstrapSshKeyId < 1) {
            throw new CloudConfigurationException(
                'ParsPack bootstrap SSH key ID must be a positive integer.',
            );
        }

        if (trim($bootstrapPrivateKey) === '') {
            throw new CloudConfigurationException(
                'ParsPack bootstrap SSH private key is not configured.',
            );
        }

        if ($fundingOverheadPercent < 0 || $fundingOverheadPercent > 100) {
            throw new CloudConfigurationException(
                'ParsPack funding overhead percent must be between 0 and 100.',
            );
        }

        $this->bootstrapSshKeyId = $bootstrapSshKeyId;
        $this->bootstrapPrivateKey = $bootstrapPrivateKey;
        $this->fundingOverheadPercent = $fundingOverheadPercent;
    }

    /** @return list<CloudRegionData> */
    public function listRegions(): array
    {
        return $this->mapper->mapRegions(
            $this->client->get(self::RESOURCE_REGIONS, $this->catalogPagination()),
        );
    }

    /** @return list<CloudSizeData> */
    public function listSizes(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapSizes(
            response: $this->client->get(self::RESOURCE_SIZES, $this->catalogPagination()),
            region: $regionId,
        );
    }

    /** @return list<CloudImageData> */
    public function listImages(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapImages(
            response: $this->client->get(self::RESOURCE_IMAGES, $this->catalogPagination()),
            region: $regionId,
        );
    }

    /** @return list<CloudRegionData> */
    public function listPurchaseRegions(): array
    {
        return $this->mapper->mapRegions(
            $this->client->getCatalog(self::RESOURCE_REGIONS, $this->catalogPagination()),
        );
    }

    /** @return list<CloudSizeData> */
    public function listPurchaseSizes(string $region): array
    {
        $regionId = $this->normalizeRegion($region);
        $sizes = $this->mapper->mapSizes(
            response: $this->client->getCatalog(self::RESOURCE_SIZES, $this->catalogPagination()),
            region: $regionId,
        );

        return array_map(
            fn (CloudSizeData $size): CloudSizeData => $this->applyFundingOverhead($size),
            $sizes,
        );
    }

    /** @return list<CloudImageData> */
    public function listPurchaseImages(string $region): array
    {
        $regionId = $this->normalizeRegion($region);

        return $this->mapper->mapImages(
            response: $this->client->getCatalog(self::RESOURCE_IMAGES, $this->catalogPagination()),
            region: $regionId,
        );
    }

    public function createServer(CreateCloudServerData $data): CreatedCloudServerData
    {
        $regionId = $this->normalizeRegion($data->regionId);
        $name = $this->normalizeServerName($data->name);
        $sizeId = $this->normalizeCatalogId($data->sizeId, 'size');
        $imageId = $this->normalizeCatalogId($data->imageId, 'image');

        if ($data->hasAnyProvisioningInfrastructure()) {
            throw new CloudValidationException(
                'ParsPack networking is managed by the provider during Coreflare provisioning and does not accept Coreflare network or security-group identifiers.',
            );
        }

        if ($data->usesSshKey()) {
            throw new CloudValidationException(
                'ParsPack provisioning uses the configured Coreflare bootstrap SSH key and does not accept a per-order SSH key.',
            );
        }

        if ($data->highAvailability) {
            throw new CloudValidationException(
                'ParsPack VM high availability is not supported by the current adapter.',
            );
        }

        $size = $this->findSize(region: $regionId, sizeId: $sizeId);

        if ($data->diskGiB !== $size->diskGiB) {
            throw new CloudValidationException(
                sprintf(
                    'ParsPack size [%s] requires its fixed [%d] GiB disk during provisioning.',
                    $size->id,
                    $size->diskGiB,
                ),
            );
        }

        $payload = [
            'name' => $name,
            'region' => $regionId,
            'size' => $sizeId,
            'image' => $imageId,
            'ssh_keys' => [$this->bootstrapSshKeyId],
            'backups' => false,
            'ipv6' => false,
        ];

        if (trim($data->initializationScript) !== '') {
            $payload['user_data'] = $data->initializationScript;
        }

        return $this->mapper->mapCreatedServer(
            $this->client->post(self::RESOURCE_SERVERS, $payload),
        );
    }

    public function findServer(string $region, string $serverId): CloudServerData
    {
        $regionId = $this->normalizeRegion($region);
        $providerServerId = $this->normalizeServerId($serverId);

        return $this->mapper->mapServer(
            response: $this->client->get($this->serverEndpoint($providerServerId)),
            region: $regionId,
        );
    }

    /** @return list<CloudServerData> */
    public function listServers(string $region): array
    {
        $regionId = $this->normalizeRegion($region);
        $servers = $this->mapper->mapServerInventory(
            $this->client->get(self::RESOURCE_SERVERS, $this->catalogPagination()),
        );

        return array_values(array_filter(
            $servers,
            static fn (CloudServerData $server): bool => $server->regionId === $regionId,
        ));
    }

    public function powerOn(string $region, string $serverId): void
    {
        $this->assertRegion($region);
        $this->submitVmAction($serverId, 'power_on');
    }

    public function powerOff(string $region, string $serverId): void
    {
        $this->assertRegion($region);
        $this->submitVmAction($serverId, 'power_off');
    }

    public function reboot(string $region, string $serverId): void
    {
        $this->assertRegion($region);
        $this->submitVmAction($serverId, 'reboot');
    }

    public function deleteServer(string $region, string $serverId): void
    {
        $this->assertRegion($region);
        $this->client->delete($this->serverEndpoint($this->normalizeServerId($serverId)));
    }

    /** @return list<CloudServerActionData> */
    public function getAvailableActions(string $region, string $serverId): array
    {
        $server = $this->findServer(region: $region, serverId: $serverId);

        if ($server->isRunning()) {
            return [
                new CloudServerActionData(self::ACTION_POWER_OFF),
                new CloudServerActionData(self::ACTION_REBOOT),
            ];
        }

        if ($server->isStopped()) {
            return [new CloudServerActionData(self::ACTION_POWER_ON)];
        }

        return [];
    }

    public function bootstrapCredential(
        CreateCloudServerData $request,
        CreatedCloudServerData $server,
    ): CloudServerBootstrapCredentialData {
        $this->normalizeRegion($request->regionId);
        $this->normalizeServerId($server->id);

        return new CloudServerBootstrapCredentialData(
            authenticationType: AuthenticationType::SSHKey,
            credential: $this->bootstrapPrivateKey,
        );
    }

    /** @return list<CloudSizeData> */
    public function listServerResizePlans(string $region, string $serverId): array
    {
        $this->normalizeServerId($serverId);

        return $this->listSizes($region);
    }

    public function findSize(string $region, string $sizeId): CloudSizeData
    {
        $sizeId = $this->normalizeCatalogId($sizeId, 'size');

        foreach ($this->listSizes($region) as $size) {
            if ($size->id === $sizeId) {
                return $size;
            }
        }

        throw new CloudResourceNotFoundException(
            sprintf('ParsPack size [%s] was not found.', $sizeId),
        );
    }

    public function calculateSize(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudSizeData {
        $size = $this->findSize(region: $region, sizeId: $sizeId);

        if ($diskGiB !== $size->diskGiB) {
            throw new CloudValidationException(
                'ParsPack custom disk sizing is not supported by the current adapter.',
            );
        }

        return $size;
    }

    public function calculateDiskPrice(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudDiskPriceData {
        return $this->fixedDiskPrice(
            $this->findSize(region: $region, sizeId: $sizeId),
            $diskGiB,
        );
    }

    public function calculatePurchaseDiskPrice(
        string $region,
        string $sizeId,
        int $diskGiB,
    ): CloudDiskPriceData {
        $sizeId = $this->normalizeCatalogId($sizeId, 'size');
        $size = null;

        foreach ($this->listPurchaseSizes($region) as $candidate) {
            if ($candidate->id === $sizeId) {
                $size = $candidate;
                break;
            }
        }

        if (! $size instanceof CloudSizeData) {
            throw new CloudResourceNotFoundException(
                sprintf('ParsPack size [%s] was not found.', $sizeId),
            );
        }

        return $this->fixedDiskPrice($size, $diskGiB);
    }

    private function applyFundingOverhead(CloudSizeData $size): CloudSizeData
    {
        if ($this->fundingOverheadPercent === 0) {
            return $size;
        }

        return new CloudSizeData(
            id: $size->id,
            name: $size->name,
            regionId: $size->regionId,
            vCpu: $size->vCpu,
            memoryMiB: $size->memoryMiB,
            diskGiB: $size->diskGiB,
            category: $size->category,
            hourlyPrice: $this->priceWithFundingOverhead($size->hourlyPrice),
            monthlyPrice: $this->priceWithFundingOverhead($size->monthlyPrice),
        );
    }

    private function priceWithFundingOverhead(?CloudPriceData $price): ?CloudPriceData
    {
        if (! $price instanceof CloudPriceData) {
            return null;
        }

        return new CloudPriceData(
            amount: $this->amountWithFundingOverhead($price->amount),
            currencyCode: $price->currencyCode,
            billingPeriod: $price->billingPeriod,
        );
    }

    private function amountWithFundingOverhead(string $amount): string
    {
        if (preg_match('/\A([0-9]+)(?:\.([0-9]+))?\z/', $amount, $matches) !== 1) {
            throw new CloudValidationException(
                'ParsPack purchase price contains an invalid amount.',
            );
        }

        $fraction = $matches[2] ?? '';
        $scale = 10 ** strlen($fraction);
        $scaledAmount = ((int) $matches[1] * $scale)
            + ($fraction === '' ? 0 : (int) $fraction);
        $numerator = $scaledAmount * (100 + $this->fundingOverheadPercent);
        $denominator = 100 * $scale;

        return (string) intdiv(
            $numerator + $denominator - 1,
            $denominator,
        );
    }

    private function fixedDiskPrice(
        CloudSizeData $size,
        int $diskGiB,
    ): CloudDiskPriceData {
        if ($diskGiB !== $size->diskGiB) {
            throw new CloudValidationException(
                'ParsPack custom disk pricing is not exposed by the current adapter.',
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

    private function submitVmAction(string $serverId, string $type): void
    {
        $providerServerId = $this->normalizeServerId($serverId);

        $this->client->post(
            sprintf('%s/actions', $this->serverEndpoint($providerServerId)),
            ['type' => $type],
        );
    }

    private function serverEndpoint(string $serverId): string
    {
        return sprintf('%s/%s', self::RESOURCE_SERVERS, $serverId);
    }

    /** @return array{page:int,per_page:int} */
    private function catalogPagination(): array
    {
        return ['page' => 1, 'per_page' => 100];
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
                'ParsPack region identifier is invalid.',
            );
        }

        return $region;
    }

    private function normalizeServerId(string $serverId): string
    {
        $serverId = strtolower(trim($serverId));

        if (preg_match('/\A[a-f0-9]{4}(?:-[a-f0-9]{4}){3}\z/', $serverId) !== 1) {
            throw new CloudValidationException(
                'ParsPack VM identifier is invalid.',
            );
        }

        return $serverId;
    }

    private function normalizeCatalogId(string $id, string $resource): string
    {
        $id = trim($id);

        if (
            $id === ''
            || strlen($id) > 128
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $id) !== 1
        ) {
            throw new CloudValidationException(
                sprintf('ParsPack %s identifier is invalid.', $resource),
            );
        }

        return $id;
    }

    private function normalizeServerName(string $name): string
    {
        $name = trim($name);

        if (
            $name === ''
            || strlen($name) > 30
            || preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\z/', $name) !== 1
        ) {
            throw new CloudValidationException(
                'ParsPack VM name must be 1-30 characters using letters, numbers, dot, underscore, or dash.',
            );
        }

        return $name;
    }
}
