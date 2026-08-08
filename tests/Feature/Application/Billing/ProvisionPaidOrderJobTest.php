<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\ProvisionPaidOrderAction;
use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Application\Billing\Jobs\VerifyProvisionedServerReadinessJob;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

final class ProvisionPaidOrderJobTest extends TestCase
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

    public function test_it_recovers_delivered_order_then_dispatches_readiness_without_reprovisioning(): void
    {
        Bus::fake();

        $user = User::factory()->create();

        $order = Order::query()->create([
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
            'status' => OrderStatus::Failed,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => now(),
        ]);

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => "xdeploy-order-{$order->id}",
            'host' => '203.0.113.77',
            'port' => 22,
            'username' => 'ubuntu',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'temporary-test-password',
            'status' => ServerStatus::Inactive,
            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'provider-existing',
            'cloud_region' => 'eu-west1-a',
            'provisioned_at' => now(),
        ]);

        $order->forceFill([
            'server_id' => $server->id,
        ])->save();

        $job = new ProvisionPaidOrderJob(
            orderId: $order->id,
        );

        $job->handle(
            app(ProvisionPaidOrderAction::class),
        );

        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );

        $this->assertSame(
            ServerStatus::Inactive,
            $server->fresh()->status,
        );

        $this->assertDatabaseCount(
            'servers',
            1,
        );

        Bus::assertDispatched(
            VerifyProvisionedServerReadinessJob::class,
            static fn (
                VerifyProvisionedServerReadinessJob $readinessJob,
            ): bool => $readinessJob->serverId === $server->id,
        );
    }
}
