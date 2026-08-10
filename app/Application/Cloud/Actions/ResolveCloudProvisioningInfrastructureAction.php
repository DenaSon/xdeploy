<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Application\Cloud\DTOs\CloudProvisioningInfrastructureData;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use InvalidArgumentException;

final readonly class ResolveCloudProvisioningInfrastructureAction
{
    public function __construct(
        private CloudProviderInterface $cloud,
    ) {}

    public function execute(
        string $regionId,
    ): CloudProvisioningInfrastructureData {
        $regionId = trim(
            $regionId,
        );

        if ($regionId === '') {
            throw new InvalidArgumentException(
                'Cloud region cannot be empty.',
            );
        }

        $network = $this->resolveNetwork(
            $regionId,
        );

        $securityGroup = $this->resolveSecurityGroup(
            $regionId,
        );

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
        $networks = array_values(
            array_filter(
                $this->cloud->listNetworks(
                    $regionId,
                ),
                static fn (
                    CloudNetworkData $network,
                ): bool => $network->regionId === $regionId
                    && $network->isActive
                    && $network->dhcpEnabled
                    && $network->ipVersion === CloudIpVersion::IPv4,
            ),
        );

        if ($networks === []) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud region [%s] has no active DHCP-enabled IPv4 network available for provisioning.',
                    $regionId,
                ),
            );
        }

        /*
         * Never select an arbitrary network.
         *
         * A region can legitimately contain multiple IPv4 networks,
         * including user/private networks. CloudNetworkData currently
         * exposes no provider-neutral "default provisioning network"
         * semantic, so silently picking the first result would be unsafe.
         *
         * For the current MVP, automatic selection is allowed only when
         * there is exactly one eligible network. If a provider/region
         * exposes more than one, we fail closed until an explicit provider
         * policy can identify the correct provisioning network.
         */
        if (count($networks) !== 1) {
            throw new CloudConfigurationException(
                sprintf(
                    'Cloud region [%s] has multiple eligible IPv4 networks; xDeploy cannot safely choose one automatically.',
                    $regionId,
                ),
            );
        }

        return $networks[0];
    }

    private function resolveSecurityGroup(
        string $regionId,
    ): CloudSecurityGroupData {
        $defaults = array_values(
            array_filter(
                $this->cloud->listSecurityGroups(
                    $regionId,
                ),
                static fn (
                    CloudSecurityGroupData $group,
                ): bool => $group->regionId === $regionId
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
}
