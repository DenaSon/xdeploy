<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\BuildCloudServerDataFromOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BuildCloudServerDataFromOrderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_provisioning_data_from_order_snapshot_and_infrastructure_defaults(): void
    {
        config()->set(
            'cloud.default',
            'arvan',
        );

        config()->set(
            'cloud.providers.arvan.defaults.network_id',
            'network-default',
        );

        config()->set(
            'cloud.providers.arvan.defaults.security_group_id',
            'security-default',
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

        $order = $this->paidOrder();

        $action =
            new BuildCloudServerDataFromOrderAction;

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

        $this->assertSame(
            'network-default',
            $data->networkId,
        );

        $this->assertSame(
            [
                'security-default',
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
