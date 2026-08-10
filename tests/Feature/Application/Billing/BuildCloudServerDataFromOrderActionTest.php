<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\BuildCloudServerDataFromOrderAction;
use App\Application\Cloud\Actions\ResolveCloudProvisioningInfrastructureAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Cloud\Contracts\CloudProviderInterface;
use App\Domain\Cloud\DTOs\CloudNetworkData;
use App\Domain\Cloud\DTOs\CloudSecurityGroupData;
use App\Domain\Cloud\Enums\CloudIpVersion;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class BuildCloudServerDataFromOrderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_provisioning_data_from_order_snapshot_and_region_scoped_infrastructure(): void
    {
        config()->set(
            'cloud.default',
            'arvan',
        );

        config()->set(
            'cloud.providers.arvan.defaults.init_script',
            '#!/bin/bash',
        );

        config()->set(
            'cloud.providers.arvan.defaults.ha_enabled',
            false,
        );

        /*
         * These old static infrastructure defaults intentionally point to
         * another region. The action must never use them anymore.
         */
        config()->set(
            'cloud.providers.arvan.defaults.network_id',
            'network-wrong-region',
        );

        config()->set(
            'cloud.providers.arvan.defaults.security_group_id',
            'security-wrong-region',
        );

        /*
         * These commercial defaults intentionally differ from the Order.
         * They must never override the paid purchase snapshot.
         */
        config()->set(
            'cloud.providers.arvan.defaults.size_id',
            'wrong-default-size',
        );

        config()->set(
            'cloud.providers.arvan.defaults.image_id',
            'wrong-default-image',
        );

        config()->set(
            'cloud.providers.arvan.defaults.disk_size',
            999,
        );

        $cloud = Mockery::mock(
            CloudProviderInterface::class,
        );

        $cloud
            ->shouldReceive('listNetworks')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudNetworkData(
                    id: 'network-eu-west1',
                    name: 'default-network',
                    regionId: 'eu-west1-a',
                    ipVersion: CloudIpVersion::IPv4,
                    cidr: null,
                    gateway: null,
                    isActive: true,
                    dhcpEnabled: true,
                ),
            ]);

        $cloud
            ->shouldReceive('listSecurityGroups')
            ->once()
            ->with('eu-west1-a')
            ->andReturn([
                new CloudSecurityGroupData(
                    id: 'security-eu-west1',
                    name: 'default',
                    regionId: 'eu-west1-a',
                    description: null,
                    isDefault: true,
                    isReadOnly: false,
                ),
            ]);

        $order = $this->paidOrder();

        $action =
            new BuildCloudServerDataFromOrderAction(
                resolveInfrastructure: new ResolveCloudProvisioningInfrastructureAction(
                    cloud: $cloud,
                ),
            );

        $data = $action->execute(
            $order,
        );

        $this->assertSame(
            "xdeploy-order-{$order->id}",
            $data->name,
        );

        $this->assertSame(
            'eu-west1-a',
            $data->regionId,
        );

        $this->assertSame(
            'eco-2-2-0',
            $data->sizeId,
        );

        $this->assertSame(
            'ubuntu-24-image',
            $data->imageId,
        );

        $this->assertSame(
            50,
            $data->diskGiB,
        );

        /*
         * Infrastructure must come from provider resources scoped to the
         * selected Order region, never from the old global config IDs.
         */
        $this->assertSame(
            'network-eu-west1',
            $data->networkId,
        );

        $this->assertSame(
            [
                'security-eu-west1',
            ],
            $data->securityGroupIds,
        );

        $this->assertNotSame(
            config(
                'cloud.providers.arvan.defaults.network_id',
            ),
            $data->networkId,
        );

        $this->assertNotSame(
            [
                config(
                    'cloud.providers.arvan.defaults.security_group_id',
                ),
            ],
            $data->securityGroupIds,
        );

        $this->assertNull(
            $data->sshKeyName,
        );

        $this->assertSame(
            '#!/bin/bash',
            $data->initializationScript,
        );

        $this->assertFalse(
            $data->highAvailability,
        );
    }

    private function paidOrder(): Order
    {
        $user = User::factory()->create();

        return Order::query()->create([
            'user_id' => $user->id,

            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',

            'image_id' => 'ubuntu-24-image',
            'image_name' => 'Ubuntu 24.04',
            'image_distribution' => 'Ubuntu',
            'image_version' => '24.04',

            'default_disk_gib' => 30,
            'selected_disk_gib' => 50,

            'period' => '2_days',
            'duration_hours' => 48,

            'provider_cost' => 1_353_600,
            'markup_percent' => 60,
            'final_amount' => 2_165_760,

            'currency' => 'IRR',

            'status' => OrderStatus::Paid,

            'quote_expires_at' => now()->addMinutes(15),

            'paid_at' => now(),
        ]);
    }
}
