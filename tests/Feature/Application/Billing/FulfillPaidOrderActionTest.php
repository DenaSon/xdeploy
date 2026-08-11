<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\FulfillCloudRenewalAction;
use App\Application\Billing\Actions\FulfillPaidOrderAction;
use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class FulfillPaidOrderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_purchase_still_dispatches_provisioning_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $order = $this->order(
            user: $user,
            type: OrderType::CloudPurchase,
            server: null,
        );

        $this->action()->execute(
            $order,
        );

        Queue::assertPushed(
            ProvisionPaidOrderJob::class,
            static fn (ProvisionPaidOrderJob $job): bool =>
                $job->orderId === $order->getKey(),
        );
    }

    public function test_paid_renewal_extends_expiration_without_dispatching_provisioning(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $server = $user
            ->servers()
            ->create([
                'name' => 'Renewable Cloud VPS',
                'host' => '203.0.113.70',
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Active,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => 'cloud-server-123',
                'cloud_region' => 'eu-west1-a',
                'provisioned_at' => now()->subDay(),
                'expires_at' => now()->addDay(),
            ]);

        $oldExpiration = $server->expires_at;

        $order = $this->order(
            user: $user,
            type: OrderType::CloudRenewal,
            server: $server,
        );

        $this->action()->execute(
            $order,
        );

        Queue::assertNotPushed(
            ProvisionPaidOrderJob::class,
        );

        $this->assertSame(
            $oldExpiration?->addHours(48)->format('Y-m-d H:i:s'),
            $server
                ->fresh()
                ?->expires_at
                ?->format('Y-m-d H:i:s'),
        );

        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );
    }

    private function action(): FulfillPaidOrderAction
    {
        return new FulfillPaidOrderAction(
            fulfillRenewal: new FulfillCloudRenewalAction,
        );
    }

    private function order(
        User $user,
        OrderType $type,
        ?Server $server,
    ): Order {
        return Order::query()->create([
            'type' => $type,
            'user_id' => $user->getKey(),
            'server_id' => $server?->getKey(),
            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',
            'image_id' => 'ubuntu-24-04-image',
            'image_name' => 'Ubuntu 24.04 LTS',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,
            'period' => '2_days',
            'duration_hours' => 48,
            'provider_cost' => 48_000,
            'markup_percent' => 75,
            'final_amount' => 84_000,
            'currency' => 'IRR',
            'status' => OrderStatus::Paid,
            'quote_expires_at' => now()->subMinute(),
            'paid_at' => now(),
        ]);
    }
}
