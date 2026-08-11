<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Payment;

use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ZarinPalRenewalCancelledCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_renewal_returns_to_renewal_page(): void
    {
        $user = User::factory()->create();

        $server = Server::query()->create([
            'user_id' => $user->id,
            'name' => 'renewal-cancel-vps',
            'host' => '203.0.113.40',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => 'arvan',
            'cloud_server_id' => 'renewal-cancel-vps-40',
            'cloud_region' => 'ir-thr-ba1',
            'provisioned_at' => now()->subDay(),
            'expires_at' => now()->addDay(),
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'type' => OrderType::Renewal,
            'server_id' => $server->id,
            'region_id' => 'ir-thr-ba1',
            'size_id' => 'eco-2-2',
            'image_id' => 'ubuntu-24',
            'image_name' => 'Ubuntu 24.04',
            'image_distribution' => 'Ubuntu',
            'image_version' => '24.04',
            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,
            'period' => '14_days',
            'duration_hours' => 336,
            'provider_cost' => 336_000,
            'markup_percent' => 75,
            'final_amount' => 588_000,
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
            'gateway_reference' => 'AUTH-RENEW-CANCEL',
            'gateway_transaction_id' => null,
            'redirect_url' => 'https://gateway.test/pay',
            'failure_code' => null,
            'verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get(
                route(
                    'payments.zarinpal.callback',
                    [
                        'Authority' => 'AUTH-RENEW-CANCEL',
                        'Status' => 'NOK',
                    ],
                ),
            )
            ->assertRedirect(
                route(
                    'panel.servers.renew',
                    [
                        'server' => $server,
                        'payment' => 'cancelled',
                    ],
                ),
            );

        $this->assertSame(
            PaymentStatus::Cancelled,
            $payment->fresh()->status,
        );
        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );
    }
}
