<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\FulfillCloudRenewalAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Exceptions\CloudServerRenewalException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class FulfillCloudRenewalActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_paid_renewal_extends_existing_expiration_and_marks_order_fulfilled(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');

        $server = $this->cloudServer(
            expiresAt: Carbon::parse('2026-08-12 12:00:00'),
        );

        $order = $this->renewalOrder(
            server: $server,
            durationHours: 48,
        );

        $result = (new FulfillCloudRenewalAction)
            ->execute(
                $order->getKey(),
            );

        $this->assertSame(
            '2026-08-14 12:00:00',
            $result->expires_at?->format('Y-m-d H:i:s'),
        );

        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );
    }

    public function test_repeated_fulfillment_does_not_add_duration_twice(): void
    {
        Carbon::setTestNow('2026-08-11 12:00:00');

        $server = $this->cloudServer(
            expiresAt: Carbon::parse('2026-08-12 12:00:00'),
        );

        $order = $this->renewalOrder(
            server: $server,
            durationHours: 48,
        );

        $action = new FulfillCloudRenewalAction;

        $action->execute(
            $order->getKey(),
        );

        $firstExpiration = $server
            ->fresh()
            ?->expires_at
            ?->format('Y-m-d H:i:s');

        $action->execute(
            $order->getKey(),
        );

        $this->assertSame(
            $firstExpiration,
            $server
                ->fresh()
                ?->expires_at
                ?->format('Y-m-d H:i:s'),
        );
    }

    public function test_payment_completed_during_protection_window_extends_from_previous_expiration(): void
    {
        Carbon::setTestNow('2026-08-11 12:05:00');

        $server = $this->cloudServer(
            expiresAt: Carbon::parse('2026-08-11 12:00:00'),
        );

        $order = $this->renewalOrder(
            server: $server,
            durationHours: 48,
        );

        (new FulfillCloudRenewalAction)
            ->execute(
                $order->getKey(),
            );

        $this->assertSame(
            '2026-08-13 12:00:00',
            $server
                ->fresh()
                ?->expires_at
                ?->format('Y-m-d H:i:s'),
        );
    }

    public function test_renewal_cannot_fulfill_after_termination_has_started(): void
    {
        $server = $this->cloudServer(
            expiresAt: now()->addHour(),
        );

        $server->forceFill([
            'termination_started_at' => now(),
        ])->saveOrFail();

        $order = $this->renewalOrder(
            server: $server,
            durationHours: 48,
        );

        $originalExpiration = $server->expires_at;

        $this->expectException(
            CloudServerRenewalException::class,
        );

        try {
            (new FulfillCloudRenewalAction)
                ->execute(
                    $order->getKey(),
                );
        } finally {
            $this->assertSame(
                $originalExpiration?->format('Y-m-d H:i:s'),
                $server
                    ->fresh()
                    ?->expires_at
                    ?->format('Y-m-d H:i:s'),
            );

            $this->assertSame(
                OrderStatus::Paid,
                $order->fresh()->status,
            );
        }
    }

    private function cloudServer(mixed $expiresAt): Server
    {
        $user = User::factory()->create();

        return $user
            ->servers()
            ->create([
                'name' => 'Renewable Cloud VPS',
                'host' => '203.0.113.60',
                'port' => 22,
                'username' => 'ubuntu',
                'status' => ServerStatus::Active,
                'cloud_provider' => 'arvan',
                'cloud_server_id' => 'cloud-server-123',
                'cloud_region' => 'eu-west1-a',
                'provisioned_at' => now()->subDay(),
                'expires_at' => $expiresAt,
            ]);
    }

    private function renewalOrder(
        Server $server,
        int $durationHours,
    ): Order {
        return Order::query()->create([
            'type' => OrderType::CloudRenewal,
            'user_id' => $server->user_id,
            'server_id' => $server->getKey(),
            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',
            'image_id' => 'ubuntu-24-04-image',
            'image_name' => 'Ubuntu 24.04 LTS',
            'image_distribution' => 'ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,
            'period' => '2_days',
            'duration_hours' => $durationHours,
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
