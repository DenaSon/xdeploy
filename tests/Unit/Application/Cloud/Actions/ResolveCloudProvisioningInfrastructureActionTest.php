<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Actions;

use App\Application\Cloud\Actions\ResolveCloudProvisioningInfrastructureAction;
use App\Domain\Cloud\Contracts\CloudProvisioningInfrastructureCatalogInterface;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class ResolveCloudProvisioningInfrastructureActionTest extends TestCase
{
    public function test_it_selects_the_default_network_when_region_contains_multiple_public_ipv4_networks(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->network(
                    id: 'public-210',
                    name: 'public210',
                    regionId: 'ir-thr-ba1',
                ),

                $this->network(
                    id: 'default-network',
                    name: 'Default network',
                    regionId: 'ir-thr-ba1',
                ),

                $this->network(
                    id: 'public-211',
                    name: 'public211',
                    regionId: 'ir-thr-ba1',
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->securityGroup(
                    id: 'sg-iran',
                    regionId: 'ir-thr-ba1',
                    isDefault: true,
                ),
            ]);

        $result = $this->action(
            $cloud,
        )->execute(
            'ir-thr-ba1',
        );

        $this->assertSame(
            'default-network',
            $result->networkId,
        );

        $this->assertSame(
            [
                'sg-iran',
            ],
            $result->securityGroupIds,
        );
    }

    public function test_it_falls_back_to_the_only_eligible_network_when_no_default_label_exists(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-southwest1-a')
            ->andReturn([
                $this->network(
                    id: 'only-network',
                    name: 'public1',
                    regionId: 'ir-southwest1-a',
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('ir-southwest1-a')
            ->andReturn([
                $this->securityGroup(
                    id: 'sg-ahwaz',
                    regionId: 'ir-southwest1-a',
                    isDefault: true,
                ),
            ]);

        $result = $this->action(
            $cloud,
        )->execute(
            'ir-southwest1-a',
        );

        $this->assertSame(
            'only-network',
            $result->networkId,
        );
    }

    public function test_it_never_reuses_resources_from_another_region(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->network(
                    id: 'germany-default',
                    name: 'Default network',
                    regionId: 'eu-west1-a',
                ),

                $this->network(
                    id: 'iran-default',
                    name: 'Default network',
                    regionId: 'ir-thr-ba1',
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->securityGroup(
                    id: 'sg-germany',
                    regionId: 'eu-west1-a',
                    isDefault: true,
                ),

                $this->securityGroup(
                    id: 'sg-iran',
                    regionId: 'ir-thr-ba1',
                    isDefault: true,
                ),
            ]);

        $result = $this->action(
            $cloud,
        )->execute(
            'ir-thr-ba1',
        );

        $this->assertSame(
            'iran-default',
            $result->networkId,
        );

        $this->assertSame(
            [
                'sg-iran',
            ],
            $result->securityGroupIds,
        );
    }

    public function test_it_fails_closed_when_multiple_eligible_networks_have_no_unique_default(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->network(
                    id: 'network-a',
                    name: 'public210',
                    regionId: 'ir-thr-ba1',
                ),

                $this->network(
                    id: 'network-b',
                    name: 'public211',
                    regionId: 'ir-thr-ba1',
                ),
            ]);

        $cloud->shouldNotReceive(
            'listSecurityGroups',
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->action(
            $cloud,
        )->execute(
            'ir-thr-ba1',
        );
    }

    public function test_it_fails_closed_when_multiple_default_networks_are_returned(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->network(
                    id: 'default-a',
                    name: 'Default network',
                    regionId: 'ir-thr-ba1',
                ),

                $this->network(
                    id: 'default-b',
                    name: ' default NETWORK ',
                    regionId: 'ir-thr-ba1',
                ),
            ]);

        $cloud->shouldNotReceive(
            'listSecurityGroups',
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->action(
            $cloud,
        )->execute(
            'ir-thr-ba1',
        );
    }

    public function test_it_rejects_region_without_an_active_dhcp_ipv4_network(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->network(
                    id: 'inactive-network',
                    name: 'Default network',
                    regionId: 'ir-thr-ba1',
                    isActive: false,
                ),

                $this->network(
                    id: 'ipv6-network',
                    name: 'Default network',
                    regionId: 'ir-thr-ba1',
                    ipVersion: CloudIpVersion::IPv6,
                ),

                $this->network(
                    id: 'no-dhcp-network',
                    name: 'Default network',
                    regionId: 'ir-thr-ba1',
                    dhcpEnabled: false,
                ),
            ]);

        $cloud->shouldNotReceive(
            'listSecurityGroups',
        );

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->action(
            $cloud,
        )->execute(
            'ir-thr-ba1',
        );
    }

    public function test_it_requires_exactly_one_default_security_group_in_the_region(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->network(
                    id: 'network-iran',
                    name: 'Default network',
                    regionId: 'ir-thr-ba1',
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('ir-thr-ba1')
            ->andReturn([
                $this->securityGroup(
                    id: 'custom-sg',
                    regionId: 'ir-thr-ba1',
                    isDefault: false,
                ),
            ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->action(
            $cloud,
        )->execute(
            'ir-thr-ba1',
        );
    }

    public function test_it_rejects_empty_region_before_calling_provider(): void
    {
        $cloud = $this->cloud();

        $cloud->shouldNotReceive(
            'listNetworks',
        );

        $cloud->shouldNotReceive(
            'listSecurityGroups',
        );

        $this->expectException(
            InvalidArgumentException::class,
        );

        $this->action(
            $cloud,
        )->execute(
            '   ',
        );
    }

    /**
     * @return CloudProvisioningInfrastructureCatalogInterface&MockInterface
     */
    private function cloud(): CloudProvisioningInfrastructureCatalogInterface
    {
        return Mockery::mock(
            CloudProvisioningInfrastructureCatalogInterface::class,
        );
    }

    private function action(
        CloudProvisioningInfrastructureCatalogInterface $cloud,
    ): ResolveCloudProvisioningInfrastructureAction {
        return new ResolveCloudProvisioningInfrastructureAction(
            cloud: $cloud,
        );
    }

    private function network(
        string $id,
        string $name,
        string $regionId,
        bool $isActive = true,
        bool $dhcpEnabled = true,
        CloudIpVersion $ipVersion = CloudIpVersion::IPv4,
    ): CloudNetworkData {
        return new CloudNetworkData(
            id: $id,
            name: $name,
            regionId: $regionId,
            ipVersion: $ipVersion,
            cidr: null,
            gateway: null,
            isActive: $isActive,
            dhcpEnabled: $dhcpEnabled,
        );
    }

    private function securityGroup(
        string $id,
        string $regionId,
        bool $isDefault,
    ): CloudSecurityGroupData {
        return new CloudSecurityGroupData(
            id: $id,
            name: $id,
            regionId: $regionId,
            description: null,
            isDefault: $isDefault,
            isReadOnly: false,
        );
    }
}
