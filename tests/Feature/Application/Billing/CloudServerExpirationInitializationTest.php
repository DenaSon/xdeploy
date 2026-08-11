<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\ProvisionPaidOrderAction;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CloudServerExpirationInitializationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cloud.default',
            'arvan',
        );
    }

    public function test_recovered_fulfilled_server_gets_expiration_from_provider_creation_time(): void
    {
        $provisionedAt = CarbonImmutable::parse(
            '2026-08-11 10:00:00',
        );

        $order = $this->order(
            status: OrderStatus::Failed,
            durationHours: 48,
        );

        $server = $this->deliveredServer(
            user: $order->user,
            order: $order,
            provisionedAt: $provisionedAt,
        );

        $order->forceFill([
            'server_id' => $server->getKey(),
        ])->save();

        $result = app(
            ProvisionPaidOrderAction::class,
        )->execute(
            (int) $order->getKey(),
        );

        $this->assertSame(
            $server->getKey(),
            $result->getKey(),
        );

        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );

        $this->assertTrue(
            $provisionedAt
                ->addHours(48)
                ->equalTo(
                    $server
                        ->fresh()
                        ->expires_at,
                ),
        );
    }

    public function test_existing_expiration_is_never_reset_during_recovery(): void
    {
        $provisionedAt = CarbonImmutable::parse(
            '2026-08-11 10:00:00',
        );

        $existingExpiration = CarbonImmutable::parse(
            '2026-08-20 12:30:00',
        );

        $order = $this->order(
            status: OrderStatus::Failed,
            durationHours: 48,
        );

        $server = $this->deliveredServer(
            user: $order->user,
            order: $order,
            provisionedAt: $provisionedAt,
        );

        $server->forceFill([
            'expires_at' => $existingExpiration,
        ])->save();

        $order->forceFill([
            'server_id' => $server->getKey(),
        ])->save();

        app(
            ProvisionPaidOrderAction::class,
        )->execute(
            (int) $order->getKey(),
        );

        $this->assertTrue(
            $existingExpiration->equalTo(
                $server
                    ->fresh()
                    ->expires_at,
            ),
        );
    }

    private function order(
        OrderStatus $status,
        int $durationHours,
    ): Order {
        $user = User::factory()->create();

        return Order::query()->create([
            'user_id' => $user->getKey(),

            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',

            'image_id' => 'ubuntu-24-image',
            'image_name' => 'Ubuntu 24.04',
            'image_distribution' => 'Ubuntu',
            'image_version' => '24.04',

            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,

            'period' => '2_days',
            'duration_hours' => $durationHours,

            'provider_cost' => 1_113_600,
            'markup_percent' => 60,
            'final_amount' => 1_781_760,
            'currency' => 'IRR',

            'status' => $status,

            'quote_expires_at' => now()->addMinutes(15),

            'paid_at' => now(),
        ]);
    }

    private function deliveredServer(
        User $user,
        Order $order,
        CarbonImmutable $provisionedAt,
    ): Server {
        return Server::query()->create([
            'user_id' => $user->getKey(),

            'name' => sprintf(
                'xdeploy-order-%d',
                $order->getKey(),
            ),

            'host' => '203.0.113.40',
            'port' => 22,
            'username' => 'ubuntu',

            'authentication_type' => AuthenticationType::Password,

            'credential' => 'temporary-test-password',

            'status' => ServerStatus::Inactive,

            'cloud_provider' => 'arvan',

            'cloud_server_id' => sprintf(
                'provider-order-%d',
                $order->getKey(),
            ),

            'cloud_region' => 'eu-west1-a',

            'provisioned_at' => $provisionedAt,
        ]);
    }
}
