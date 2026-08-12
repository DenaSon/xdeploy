<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Payment;

use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Infrastructure\Payment\ZarinPal\ZarinPalPaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class ZarinPalRetryableVerificationCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_retryable_verification_failure_keeps_provisioning_order_pending_and_offers_retry(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $user->id,
            'type' => OrderType::Provisioning,
            'server_id' => null,
            'region_id' => 'ir-thr-ba1',
            'size_id' => 'eco-2-2',
            'image_id' => 'ubuntu-24',
            'image_name' => 'Ubuntu 24.04',
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
            'gateway_reference' => 'AUTH-PROVISIONING-500',
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
            ->andThrow(
                new ZarinPalPaymentException(
                    'Temporary ZarinPal verification failure.',
                    500,
                ),
            );

        $this->app->instance(
            PaymentGatewayInterface::class,
            $gateway,
        );

        $callbackUrl = route(
            'payments.zarinpal.callback',
            [
                'Authority' => 'AUTH-PROVISIONING-500',
                'Status' => 'OK',
            ],
        );

        $response = $this
            ->actingAs($user)
            ->get($callbackUrl);

        $response
            ->assertRedirect(
                route(
                    'panel.orders.show',
                    $order,
                ),
            )
            ->assertSessionHas(
                'payment_verification_pending',
                true,
            )
            ->assertSessionHas(
                'payment_verification_retry_url',
                $callbackUrl,
            );

        $this->assertSame(
            PaymentStatus::Pending,
            $payment->fresh()->status,
        );

        $this->assertNull(
            $payment->fresh()->verified_at,
        );

        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );

        Queue::assertNotPushed(
            ProvisionPaidOrderJob::class,
        );
    }
}
