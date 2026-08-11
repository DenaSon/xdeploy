<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Payment;

use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentVerificationData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class ZarinPalRenewalCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_verified_renewal_payment_extends_server_and_never_queues_provisioning(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-11 18:00:00'),
        );

        Queue::fake();

        $user = User::factory()->create();

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => 'renewable-cloud-vps',
            'host' => '203.0.113.10',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'cloud-renewal-callback',
            'cloud_region' => 'eu-west1-a',
            'provisioned_at' => now()->subDay(),
            'expires_at' => Carbon::parse('2026-08-12 06:00:00'),
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'type' => OrderType::Renewal,
            'server_id' => $server->id,
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
            'provider_cost' => 1_000_000,
            'markup_percent' => 75,
            'final_amount' => 1_750_000,
            'currency' => 'IRR',
            'status' => OrderStatus::PendingPayment,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => null,
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'gateway' => 'zarinpal',
            'amount' => $order->final_amount,
            'currency' => $order->currency,
            'status' => PaymentStatus::Pending,
            'gateway_reference' => 'AUTH-RENEWAL',
            'gateway_transaction_id' => null,
            'redirect_url' => 'https://gateway.test/pay',
            'failure_code' => null,
            'verified_at' => null,
        ]);

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('zarinpal');

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn(
                new PaymentVerificationData(
                    reference: 'AUTH-RENEWAL',
                    transactionId: 'TX-RENEWAL',
                    amount: 1_750_000,
                    verifiedAt: new DateTimeImmutable(
                        '2026-08-11 18:00:10',
                    ),
                ),
            );

        $this->app->instance(
            PaymentGatewayInterface::class,
            $gateway,
        );

        $this->actingAs($user)
            ->get(
                route(
                    'payments.zarinpal.callback',
                    [
                        'Authority' => 'AUTH-RENEWAL',
                        'Status' => 'OK',
                    ],
                ),
            )
            ->assertRedirect(
                route(
                    'panel.orders.show',
                    $order,
                ),
            );

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->fresh()->status,
        );
        $this->assertSame(
            OrderStatus::Fulfilled,
            $order->fresh()->status,
        );
        $this->assertSame(
            '2026-08-14 06:00:00',
            $server->fresh()->expires_at?->format('Y-m-d H:i:s'),
        );

        Queue::assertNotPushed(
            ProvisionPaidOrderJob::class,
        );
    }
}
