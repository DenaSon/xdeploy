<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Application\Cloud\DTOs\CloudProvisioningInfrastructureData;
use App\Domain\Cloud\Contracts\CloudProvisioningInfrastructureCatalogInterface;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use InvalidArgumentException;

final readonly class ResolveCloudProvisioningInfrastructureAction
{
    private const string DEFAULT_NETWORK_NAME = 'default network';

    public function __construct(
        private CloudProvisioningInfrastructureCatalogInterface $cloud,
    ) {}

    public function execute(
        string $regionId,
    ): CloudProvisioningInfrastructureData {
        $regionId = trim($regionId);

        if ($regionId === '') {
            throw new InvalidArgumentException(
                'Cloud region cannot be empty.',
            );
        }

        $network = $this->resolveNetwork($regionId);
        $securityGroup = $this->resolveSecurityGroup($regionId);

        return new CloudProvisioningInfrastructureData(
            networkId: $network->id,
            securityGroupIds: [
                $securityGroup->id,
            ],
        );
    }

    private function resolveNetwork(
        string $regionId,
    ): CloudNetworkData {
        $eligibleNetworks = array_values(
            array_filter(
                $this->cloud->listNetworks($regionId),
                static fn (CloudNetworkData $network): bool => $network->regionId === $regionId
                    && $network->isActive
                    && $network->dhcpEnabled
                    && $network->ipVersion === CloudIpVersion::IPv4,
            ),
        );

        if ($eligibleNetworks === []) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud region [%s] has no active DHCP-enabled IPv4 network available for provisioning.',
                    $regionId,
                ),
            );
        }

        $defaultNetworks = array_values(
            array_filter(
                $eligibleNetworks,
                fn (CloudNetworkData $network): bool => $this->isDefaultNetwork($network),
            ),
        );

        if (count($defaultNetworks) === 1) {
            return $defaultNetworks[0];
        }

        if (count($defaultNetworks) > 1) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud region [%s] exposes multiple default networks; xDeploy cannot safely choose one.',
                    $regionId,
                ),
            );
        }

        if (count($eligibleNetworks) === 1) {
            return $eligibleNetworks[0];
        }

        throw new CloudConfigurationException(
            sprintf(
                'Cloud region [%s] has multiple eligible IPv4 networks but no unique default network.',
                $regionId,
            ),
        );
    }

    private function resolveSecurityGroup(
        string $regionId,
    ): CloudSecurityGroupData {
        $defaults = array_values(
            array_filter(
                $this->cloud->listSecurityGroups($regionId),
                static fn (CloudSecurityGroupData $group): bool => $group->regionId === $regionId
                    && $group->isDefault,
            ),
        );

        if ($defaults === []) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud region [%s] has no default security group available for provisioning.',
                    $regionId,
                ),
            );
        }

        if (count($defaults) !== 1) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud region [%s] exposes multiple default security groups; xDeploy cannot safely choose one.',
                    $regionId,
                ),
            );
        }

        return $defaults[0];
    }

    private function isDefaultNetwork(
        CloudNetworkData $network,
    ): bool {
        return mb_strtolower(trim($network->name)) === self::DEFAULT_NETWORK_NAME;
    }
}
