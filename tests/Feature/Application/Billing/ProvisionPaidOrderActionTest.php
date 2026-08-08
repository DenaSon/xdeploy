<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\ProvisionPaidOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Exceptions\OrderNotProvisionableException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProvisionPaidOrderActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    public function test_it_rejects_order_that_is_not_paid(): void
    {
        $order = $this->order(
            OrderStatus::PendingPayment,
        );

        $this->expectException(
            OrderNotProvisionableException::class,
        );

        try {
            $this->action()->execute(
                $order->id,
            );
        } finally {
            $this->assertSame(
                OrderStatus::PendingPayment,
                $order->fresh()->status,
            );

            $this->assertNull(
                $order->fresh()->server_id,
            );
        }
    }

    public function test_fulfilled_order_returns_the_same_server_idempotently(): void
    {
        $order = $this->order(
            OrderStatus::Fulfilled,
        );

        $server = $this->server(
            user: $order->user,
            name: "xdeploy-order-{$order->id}",
            status: ServerStatus::Active,
        );

        $order->forceFill([
            'server_id' => $server->id,
        ])->save();

        $result = $this->action()->execute(
            $order->id,
        );

        $this->assertSame(
            $server->id,
            $result->id,
        );

        $freshOrder = $order->fresh();

        $this->assertSame(
            OrderStatus::Fulfilled,
            $freshOrder->status,
        );

        $this->assertSame(
            $server->id,
            $freshOrder->server_id,
        );

        $this->assertDatabaseCount(
            'servers',
            1,
        );
    }

    public function test_reinvoking_provisioning_order_does_not_create_another_server(): void
    {
        $order = $this->order(
            OrderStatus::Provisioning,
        );

        $server = $this->server(
            user: $order->user,
            name: "xdeploy-order-{$order->id}",
            status: ServerStatus::Inactive,
        );

        $this->expectException(
            OrderNotProvisionableException::class,
        );

        try {
            $this->action()->execute(
                $order->id,
            );
        } finally {
            $freshOrder = $order->fresh();

            $this->assertSame(
                OrderStatus::Provisioning,
                $freshOrder->status,
            );

            $this->assertSame(
                $server->id,
                $freshOrder->server_id,
            );

            $this->assertDatabaseCount(
                'servers',
                1,
            );
        }
    }

    public function test_failed_order_with_delivered_inactive_server_is_recovered_as_fulfilled_without_reprovisioning(): void
    {
        $order = $this->order(
            OrderStatus::Failed,
        );

        $server = $this->server(
            user: $order->user,
            name: "xdeploy-order-{$order->id}",
            status: ServerStatus::Inactive,
        );

        $server->forceFill([
            'host' => '203.0.113.77',
        ])->save();

        $order->forceFill([
            'server_id' => $server->id,
        ])->save();

        $result = $this->action()->execute(
            $order->id,
        );

        $this->assertSame(
            $server->id,
            $result->id,
        );

        $freshOrder = $order->fresh();

        $this->assertSame(
            OrderStatus::Fulfilled,
            $freshOrder->status,
        );

        $this->assertSame(
            $server->id,
            $freshOrder->server_id,
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $server->fresh()->status,
        );

        $this->assertSame(
            '203.0.113.77',
            $server->fresh()->host,
        );

        $this->assertDatabaseCount(
            'servers',
            1,
        );
    }

    public function test_fulfilled_order_without_server_is_never_reprovisioned(): void
    {
        $order = $this->order(
            OrderStatus::Fulfilled,
        );

        $this->expectException(
            OrderNotProvisionableException::class,
        );

        try {
            $this->action()->execute(
                $order->id,
            );
        } finally {
            $this->assertDatabaseCount(
                'servers',
                0,
            );

            $this->assertSame(
                OrderStatus::Fulfilled,
                $order->fresh()->status,
            );
        }
    }

    private function action(): ProvisionPaidOrderAction
    {
        return app(
            ProvisionPaidOrderAction::class,
        );
    }

    private function order(
        OrderStatus $status,
    ): Order {
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
            'selected_disk_gib' => 30,

            'period' => '2_days',
            'duration_hours' => 48,

            'provider_cost' => 1_113_600,
            'markup_percent' => 60,
            'final_amount' => 1_781_760,

            'currency' => 'IRR',

            'status' => $status,

            'quote_expires_at' => now()->addMinutes(15),

            'paid_at' => $status === OrderStatus::PendingPayment
                    ? null
                    : now(),
        ]);
    }

    private function server(
        User $user,
        string $name,
        ServerStatus $status,
    ): Server {
        return Server::query()->create([
            'user_id' => $user->id,

            'name' => $name,

            'host' => $status === ServerStatus::Active
                ? '203.0.113.10'
                : null,

            'port' => 22,
            'username' => 'ubuntu',

            'authentication_type' => AuthenticationType::Password,

            'credential' => 'temporary-test-password',

            'status' => $status,

            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'provider-'.$name,

            'cloud_region' => 'eu-west1-a',

            'provisioned_at' => now(),
        ]);
    }
}
