<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudProvisioningInfrastructureCatalogInterface;
use App\Domain\Cloud\Contracts\CloudQuotaReaderInterface;
use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudProviderException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use Illuminate\Console\Command;

final class CheckCloudProviderCommand extends Command
{
    protected $signature = 'cloud:check
        {--provider= : Cloud provider to check instead of cloud.default}';

    protected $description = 'Run a read-only check of a cloud provider and its configured catalog defaults.';

    public function __construct(
        private readonly CloudProviderRegistryInterface $providers,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $providerType = $this->providerType(
                $this->selectedDriver(),
            );
            $driver = $providerType->value;
            $provider = $this->providers->resolve($providerType);

            $regionId = $this->requiredConfigString(
                "cloud.providers.{$driver}.region",
            );
            $defaultSizeId = $this->requiredConfigString(
                "cloud.providers.{$driver}.defaults.size_id",
            );
            $defaultImageId = $this->requiredConfigString(
                "cloud.providers.{$driver}.defaults.image_id",
            );

            $this->components->info(
                'Checking cloud provider connection...',
            );

            $regions = $provider->listRegions();

            /** @var CloudRegionData $region */
            $region = $this->findById(
                items: $regions,
                id: $regionId,
                resource: 'region',
            );

            if (! $region->isVisible) {
                throw new CloudConfigurationException(
                    'The configured cloud region is not visible.',
                );
            }

            if (! $region->canCreateServers) {
                throw new CloudConfigurationException(
                    'The configured cloud region cannot create servers.',
                );
            }

            $sizes = $provider->listSizes($regionId);

            /** @var CloudSizeData $size */
            $size = $this->findById(
                items: $sizes,
                id: $defaultSizeId,
                resource: 'size',
            );

            $images = $provider->listImages($regionId);

            /** @var CloudImageData $image */
            $image = $this->findById(
                items: $images,
                id: $defaultImageId,
                resource: 'image',
            );

            $this->newLine();
            $this->components->info('Cloud provider check passed.');
            $this->line(sprintf('Provider: %s', $driver));
            $this->line(
                sprintf(
                    'Region: %s (%s)',
                    $region->id,
                    $region->displayName ?? 'Unnamed',
                ),
            );
            $this->line(sprintf('Regions: %d', count($regions)));
            $this->line(
                sprintf(
                    'Sizes: %d | Default: %s (%d vCPU, %d MiB RAM, %d GiB disk)',
                    count($sizes),
                    $size->id,
                    $size->vCpu,
                    $size->memoryMiB,
                    $size->diskGiB,
                ),
            );
            $this->line(
                sprintf(
                    'Images: %d | Default: %s',
                    count($images),
                    $image->name,
                ),
            );

            $this->checkProvisioningInfrastructure(
                provider: $providerType,
                driver: $driver,
                regionId: $regionId,
            );

            $this->checkQuota(
                provider: $providerType,
                regionId: $regionId,
            );

            $this->checkInventory(
                provider: $providerType,
                regionId: $regionId,
            );

            $this->components->warn(
                'SSH keys: skipped because the successful response schema is not verified.',
            );

            return self::SUCCESS;
        } catch (CloudProviderException $exception) {
            $this->newLine();
            $this->components->error(
                $this->safeFailureMessage($exception),
            );

            return self::FAILURE;
        }
    }

    private function checkProvisioningInfrastructure(
        CloudProviderType $provider,
        string $driver,
        string $regionId,
    ): void {
        if (! $this->providers->supportsCapability(
            provider: $provider,
            capability: CloudProvisioningInfrastructureCatalogInterface::class,
        )) {
            $this->components->warn(
                'Networks and security groups: skipped because the provider manages provisioning infrastructure implicitly.',
            );

            return;
        }

        /** @var CloudProvisioningInfrastructureCatalogInterface $infrastructure */
        $infrastructure = $this->providers->resolveCapability(
            provider: $provider,
            capability: CloudProvisioningInfrastructureCatalogInterface::class,
        );

        $defaultNetworkId = $this->requiredConfigString(
            "cloud.providers.{$driver}.defaults.network_id",
        );
        $defaultSecurityGroupId = $this->requiredConfigString(
            "cloud.providers.{$driver}.defaults.security_group_id",
        );
        $defaultSecurityGroupName = $this->requiredConfigString(
            "cloud.providers.{$driver}.defaults.security_group_name",
        );

        $networks = $infrastructure->listNetworks($regionId);

        /** @var CloudNetworkData $network */
        $network = $this->findById(
            items: $networks,
            id: $defaultNetworkId,
            resource: 'network',
        );

        $securityGroups = $infrastructure->listSecurityGroups($regionId);

        /** @var CloudSecurityGroupData $securityGroup */
        $securityGroup = $this->findById(
            items: $securityGroups,
            id: $defaultSecurityGroupId,
            resource: 'security group',
        );

        if ($securityGroup->name !== $defaultSecurityGroupName) {
            throw new CloudConfigurationException(
                'The configured default security group name does not match its identifier.',
            );
        }

        $this->line(
            sprintf(
                'Networks: %d | Default: %s',
                count($networks),
                $network->name,
            ),
        );
        $this->line(
            sprintf(
                'Security groups: %d | Default: %s',
                count($securityGroups),
                $securityGroup->name,
            ),
        );
    }

    private function checkQuota(
        CloudProviderType $provider,
        string $regionId,
    ): void {
        if (! $this->providers->supportsCapability(
            provider: $provider,
            capability: CloudQuotaReaderInterface::class,
        )) {
            $this->components->warn(
                'Quota: skipped because the provider does not expose a compatible quota capability.',
            );

            return;
        }

        /** @var CloudQuotaReaderInterface $quotaReader */
        $quotaReader = $this->providers->resolveCapability(
            provider: $provider,
            capability: CloudQuotaReaderInterface::class,
        );

        $quota = $quotaReader->getQuota($regionId);

        $this->line(
            sprintf(
                'Quota instances: %s',
                $this->formatQuota(
                    used: $quota->instancesUsed,
                    limit: $quota->instancesLimit,
                ),
            ),
        );
        $this->line(
            sprintf(
                'Quota vCPU: %s',
                $this->formatQuota(
                    used: $quota->vCpuUsed,
                    limit: $quota->vCpuLimit,
                ),
            ),
        );
        $this->line(
            sprintf(
                'Quota memory: %s MiB',
                $this->formatQuota(
                    used: $quota->memoryMiBUsed,
                    limit: $quota->memoryMiBLimit,
                ),
            ),
        );
    }

    private function checkInventory(
        CloudProviderType $provider,
        string $regionId,
    ): void {
        if (! $this->providers->supportsCapability(
            provider: $provider,
            capability: CloudServerInventoryInterface::class,
        )) {
            $this->components->warn(
                'Server inventory: skipped because the provider does not expose a compatible inventory capability.',
            );

            return;
        }

        /** @var CloudServerInventoryInterface $inventory */
        $inventory = $this->providers->resolveCapability(
            provider: $provider,
            capability: CloudServerInventoryInterface::class,
        );

        $servers = $inventory->listServers($regionId);

        $this->line(
            sprintf(
                'Server inventory: %d',
                count($servers),
            ),
        );
    }

    private function selectedDriver(): string
    {
        $option = $this->option('provider');

        if ($option === null) {
            return $this->requiredConfigString('cloud.default');
        }

        if (! is_string($option) || trim($option) === '') {
            throw new CloudConfigurationException(
                'Cloud provider option cannot be empty.',
            );
        }

        return trim($option);
    }

    private function providerType(string $driver): CloudProviderType
    {
        $provider = CloudProviderType::tryFrom(
            strtolower(trim($driver)),
        );

        if (! $provider instanceof CloudProviderType) {
            throw new CloudConfigurationException(
                sprintf(
                    'The cloud provider [%s] is not supported.',
                    $driver,
                ),
            );
        }

        return $provider;
    }

    /**
     * @template T of object
     * @param  list<T>  $items
     * @return T
     */
    private function findById(
        array $items,
        string $id,
        string $resource,
    ): object {
        foreach ($items as $item) {
            if (property_exists($item, 'id') && $item->id === $id) {
                return $item;
            }
        }

        throw new CloudConfigurationException(
            sprintf(
                'The configured default cloud %s is unavailable.',
                $resource,
            ),
        );
    }

    private function requiredConfigString(string $key): string
    {
        $value = config($key);

        if (! is_string($value) || trim($value) === '') {
            throw new CloudConfigurationException(
                sprintf(
                    'Required cloud configuration [%s] is missing.',
                    $key,
                ),
            );
        }

        return trim($value);
    }

    private function formatQuota(
        ?int $used,
        ?int $limit,
    ): string {
        $usedValue = $used === null ? 'unknown' : (string) $used;
        $limitValue = $limit === null ? 'unlimited' : (string) $limit;

        return "{$usedValue} / {$limitValue}";
    }

    private function safeFailureMessage(
        CloudProviderException $exception,
    ): string {
        return match (true) {
            $exception instanceof CloudAuthenticationException => 'Cloud provider authentication failed.',
            $exception instanceof CloudAuthorizationException => 'Cloud provider permission is insufficient.',
            $exception instanceof CloudConnectionException => 'Could not connect to the cloud provider.',
            $exception instanceof CloudRateLimitException => 'Cloud provider rate limit was exceeded.',
            $exception instanceof CloudConfigurationException => 'Cloud provider configuration is invalid.',
            $exception instanceof CloudValidationException => 'Cloud provider rejected the request.',
            $exception instanceof CloudUnexpectedResponseException => 'Cloud provider returned an unexpected response.',
            default => 'Cloud provider check failed.',
        };
    }
}
