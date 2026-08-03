<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudImageData;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudRegionData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\DTOs\CloudSizeData;
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
    protected $signature = 'cloud:check';

    protected $description = 'Check the configured cloud provider and catalog defaults.';

    public function __construct(
        private readonly CloudProviderInterface $provider,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $driver = $this->requiredConfigString(
                'cloud.default',
            );

            $regionId = $this->requiredConfigString(
                "cloud.providers.{$driver}.region",
            );

            $defaultSizeId = $this->requiredConfigString(
                "cloud.providers.{$driver}.defaults.size_id",
            );

            $defaultImageId = $this->requiredConfigString(
                "cloud.providers.{$driver}.defaults.image_id",
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

            $this->components->info(
                'Checking cloud provider connection...',
            );

            $regions = $this->provider->listRegions();

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

            $sizes = $this->provider->listSizes($regionId);

            /** @var CloudSizeData $size */
            $size = $this->findById(
                items: $sizes,
                id: $defaultSizeId,
                resource: 'size',
            );

            $images = $this->provider->listImages($regionId);

            /** @var CloudImageData $image */
            $image = $this->findById(
                items: $images,
                id: $defaultImageId,
                resource: 'image',
            );

            $networks = $this->provider->listNetworks(
                $regionId,
            );

            /** @var CloudNetworkData $network */
            $network = $this->findById(
                items: $networks,
                id: $defaultNetworkId,
                resource: 'network',
            );

            $securityGroups = $this->provider
                ->listSecurityGroups($regionId);

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

            $quota = $this->provider->getQuota(
                $regionId,
            );

            $this->newLine();

            $this->components->info(
                'Cloud provider check passed.',
            );

            $this->line(
                sprintf(
                    'Provider: %s',
                    $driver,
                ),
            );

            $this->line(
                sprintf(
                    'Region: %s (%s)',
                    $region->id,
                    $region->displayName ?? 'Unnamed',
                ),
            );

            $this->line(
                sprintf(
                    'Regions: %d',
                    count($regions),
                ),
            );

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

    /**
     * @template T of object
     *
     * @param  list<T>  $items
     * @return T
     */
    private function findById(
        array $items,
        string $id,
        string $resource,
    ): object {
        foreach ($items as $item) {
            if (
                property_exists($item, 'id')
                && $item->id === $id
            ) {
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

    private function requiredConfigString(
        string $key,
    ): string {
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
        $usedValue = $used === null
            ? 'unknown'
            : (string) $used;

        $limitValue = $limit === null
            ? 'unlimited'
            : (string) $limit;

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
