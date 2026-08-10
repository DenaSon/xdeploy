<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Cloud\Actions;

use App\Application\Cloud\Actions\ResolveCloudProvisioningInfrastructureAction;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
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
    public function test_it_resolves_network_and_default_security_group_from_the_selected_region(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->network(
                    id: 'network-iran',
                    regionId: 'ir-thr1',
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->securityGroup(
                    id: 'sg-iran',
                    regionId: 'ir-thr1',
                    isDefault: true,
                ),
            ]);

        $result = $this->action(
            $cloud,
        )->execute(
            'ir-thr1',
        );

        $this->assertSame(
            'network-iran',
            $result->networkId,
        );

        $this->assertSame(
            [
                'sg-iran',
            ],
            $result->securityGroupIds,
        );
    }

    public function test_it_never_reuses_resources_from_another_region(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->network(
                    id: 'network-germany',
                    regionId: 'eu-west1-a',
                ),

                $this->network(
                    id: 'network-iran',
                    regionId: 'ir-thr1',
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->securityGroup(
                    id: 'sg-germany',
                    regionId: 'eu-west1-a',
                    isDefault: true,
                ),

                $this->securityGroup(
                    id: 'sg-iran',
                    regionId: 'ir-thr1',
                    isDefault: true,
                ),
            ]);

        $result = $this->action(
            $cloud,
        )->execute(
            'ir-thr1',
        );

        $this->assertSame(
            'network-iran',
            $result->networkId,
        );

        $this->assertSame(
            [
                'sg-iran',
            ],
            $result->securityGroupIds,
        );
    }

    public function test_it_fails_closed_when_multiple_networks_are_equally_eligible(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->network(
                    id: 'network-a',
                    regionId: 'ir-thr1',
                ),

                $this->network(
                    id: 'network-b',
                    regionId: 'ir-thr1',
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
            'ir-thr1',
        );
    }

    public function test_it_rejects_region_without_an_active_dhcp_ipv4_network(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->network(
                    id: 'inactive-network',
                    regionId: 'ir-thr1',
                    isActive: false,
                ),

                $this->network(
                    id: 'ipv6-network',
                    regionId: 'ir-thr1',
                    ipVersion: CloudIpVersion::IPv6,
                ),

                $this->network(
                    id: 'no-dhcp-network',
                    regionId: 'ir-thr1',
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
            'ir-thr1',
        );
    }

    public function test_it_requires_exactly_one_default_security_group_in_the_region(): void
    {
        $cloud = $this->cloud();

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->network(
                    id: 'network-iran',
                    regionId: 'ir-thr1',
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('ir-thr1')
            ->andReturn([
                $this->securityGroup(
                    id: 'custom-sg',
                    regionId: 'ir-thr1',
                    isDefault: false,
                ),
            ]);

        $this->expectException(
            CloudConfigurationException::class,
        );

        $this->action(
            $cloud,
        )->execute(
            'ir-thr1',
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
     * @return CloudProviderInterface&MockInterface
     */
    private function cloud(): CloudProviderInterface
    {
        return Mockery::mock(
            CloudProviderInterface::class,
        );
    }

    private function action(
        CloudProviderInterface $cloud,
    ): ResolveCloudProvisioningInfrastructureAction {
        return new ResolveCloudProvisioningInfrastructureAction(
            cloud: $cloud,
        );
    }

    private function network(
        string $id,
        string $regionId,
        bool $isActive = true,
        bool $dhcpEnabled = true,
        CloudIpVersion $ipVersion = CloudIpVersion::IPv4,
    ): CloudNetworkData {
        return new CloudNetworkData(
            id: $id,
            name: $id,
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
