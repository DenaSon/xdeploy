<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\FulfillPaidRenewalOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class FulfillPaidRenewalOrderActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_extends_from_current_expiry_without_losing_remaining_time(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 18:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: Carbon::parse('2026-08-12 06:00:00'),
        );
        $order = $this->renewalOrder(
            user: $user,
            server: $server,
            durationHours: 48,
        );

        $result = (new FulfillPaidRenewalOrderAction)
            ->execute(
                (int) $order->id,
            );

        $this->assertSame(
            '2026-08-14 06:00:00',
            $result->expires_at?->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );
    }

    public function test_repeated_fulfillment_never_adds_duration_twice(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 18:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: Carbon::parse('2026-08-12 06:00:00'),
        );
        $order = $this->renewalOrder(
            user: $user,
            server: $server,
            durationHours: 48,
        );
        $action = new FulfillPaidRenewalOrderAction;

        $first = $action->execute(
            (int) $order->id,
        );
        $second = $action->execute(
            (int) $order->id,
        );

        $this->assertSame(
            '2026-08-14 06:00:00',
            $first->expires_at?->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-08-14 06:00:00',
            $second->expires_at?->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-08-14 06:00:00',
            $server->fresh()->expires_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_paid_callback_just_after_expiry_extends_from_now_if_termination_has_not_started(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 18:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: Carbon::parse('2026-08-11 17:59:59'),
        );
        $order = $this->renewalOrder(
            user: $user,
            server: $server,
            durationHours: 48,
        );

        $result = (new FulfillPaidRenewalOrderAction)
            ->execute(
                (int) $order->id,
            );

        $this->assertSame(
            '2026-08-13 18:00:00',
            $result->expires_at?->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );
    }

    public function test_it_refuses_to_race_termination_after_termination_has_started(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 18:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: Carbon::parse('2026-08-11 17:59:00'),
            terminationStartedAt: Carbon::parse('2026-08-11 18:00:00'),
        );
        $order = $this->renewalOrder(
            user: $user,
            server: $server,
            durationHours: 48,
        );

        $this->expectException(
            CloudServerRenewalException::class,
        );

        try {
            (new FulfillPaidRenewalOrderAction)
                ->execute(
                    (int) $order->id,
                );
        } finally {
            $this->assertSame(
                '2026-08-11 17:59:00',
                $server->fresh()->expires_at?->format('Y-m-d H:i:s'),
            );
            $this->assertSame(
                OrderStatus::Paid,
                $order->fresh()->status,
            );
        }
    }

    public function test_distinct_paid_renewal_orders_add_their_purchased_time_sequentially(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 18:00:00'),
        );

        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: Carbon::parse('2026-08-12 00:00:00'),
        );
        $firstOrder = $this->renewalOrder(
            user: $user,
            server: $server,
            durationHours: 48,
        );
        $secondOrder = $this->renewalOrder(
            user: $user,
            server: $server,
            durationHours: 336,
            period: '14_days',
        );
        $action = new FulfillPaidRenewalOrderAction;

        $action->execute(
            (int) $firstOrder->id,
        );
        $result = $action->execute(
            (int) $secondOrder->id,
        );

        $this->assertSame(
            '2026-08-28 00:00:00',
            $result->expires_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_it_rejects_provisioning_order(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            expiresAt: now()->addDay(),
        );
        $order = $this->renewalOrder(
            user: $user,
            server: $server,
            durationHours: 48,
            type: OrderType::Provisioning,
        );

        $this->expectException(
            CloudServerRenewalException::class,
        );

        (new FulfillPaidRenewalOrderAction)
            ->execute(
                (int) $order->id,
            );
    }

    private function cloudServer(
        User $user,
        \DateTimeInterface $expiresAt,
        ?\DateTimeInterface $terminationStartedAt = null,
    ): Server {
        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'cloud-vps',
            'host' => '203.0.113.10',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'cloud-'.uniqid(),
            'cloud_region' => 'eu-west1-a',
            'provisioned_at' => now()->subDay(),
            'expires_at' => $expiresAt,
            'termination_started_at' => $terminationStartedAt,
        ]);
    }

    private function renewalOrder(
        User $user,
        Server $server,
        int $durationHours,
        string $period = '2_days',
        OrderType $type = OrderType::Renewal,
    ): Order {
        return Order::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'server_id' => $server->id,
            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',
            'image_id' => 'ubuntu-24-04-image',
            'image_name' => 'Ubuntu 24.04 LTS',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,
            'period' => $period,
            'duration_hours' => $durationHours,
            'provider_cost' => 1_000_000,
            'markup_percent' => 75,
            'final_amount' => 1_750_000,
            'currency' => 'IRR',
            'status' => OrderStatus::Paid,
            'quote_expires_at' => now()->subMinute(),
            'paid_at' => now(),
        ]);
    }
}
