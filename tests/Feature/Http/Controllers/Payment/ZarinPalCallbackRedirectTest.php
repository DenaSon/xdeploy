<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Payment;

use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentVerificationData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class ZarinPalCallbackRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_gateway_return_redirects_to_order_status_page(): void
    {
        $user = User::factory()->create();

        $order = $this->order(
            $user,
        );

        $payment = $this->payment(
            order: $order,
            reference: 'cancel-authority',
        );

        $this->actingAs(
            $user,
        )
            ->get(
                route(
                    'payments.zarinpal.callback',
                    [
                        'Authority' => 'cancel-authority',
                        'Status' => 'NOK',
                    ],
                ),
            )
            ->assertRedirect(
                route(
                    'panel.orders.show',
                    $order,
                ),
            );

        $payment->refresh();
        $order->refresh();

        $this->assertSame(
            PaymentStatus::Cancelled,
            $payment->status,
        );

        /*
         * Cancelling one gateway attempt does not cancel the commercial
         * Order. The customer may retry payment while the quote is valid.
         */
        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->status,
        );
    }

    public function test_verified_payment_redirects_to_order_status_and_queues_provisioning(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        $order = $this->order(
            $user,
        );

        $payment = $this->payment(
            order: $order,
            reference: 'paid-authority',
        );

        $verifiedAt =
            new DateTimeImmutable(
                '2026-08-10T12:00:00+00:00',
            );

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
                    reference: 'paid-authority',
                    transactionId: 'txn-123',
                    amount: 2_165_760,
                    verifiedAt: $verifiedAt,
                ),
            );

        $this->app->instance(
            PaymentGatewayInterface::class,
            $gateway,
        );

        $this->actingAs(
            $user,
        )
            ->get(
                route(
                    'payments.zarinpal.callback',
                    [
                        'Authority' => 'paid-authority',
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

        $payment->refresh();
        $order->refresh();

        $this->assertSame(
            PaymentStatus::Paid,
            $payment->status,
        );

        $this->assertSame(
            'txn-123',
            $payment->gateway_transaction_id,
        );

        $this->assertSame(
            OrderStatus::Paid,
            $order->status,
        );

        Queue::assertPushed(
            ProvisionPaidOrderJob::class,
            static fn (
                ProvisionPaidOrderJob $job,
            ): bool => $job->orderId
                === $order->getKey(),
        );
    }

    public function test_callback_without_authority_still_returns_validation_error(): void
    {
        $this->get(
            route(
                'payments.zarinpal.callback',
                [
                    'Status' => 'OK',
                ],
            ),
        )
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Missing payment authority.',
            ]);
    }

    private function order(
        User $user,
    ): Order {
        return Order::query()->create([
            'user_id' => $user->getKey(),

            'region_id' => 'ir-thr-ba1',
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

            'status' => OrderStatus::PendingPayment,

            'quote_expires_at' => now()->addMinutes(15),

            'paid_at' => null,
        ]);
    }

    private function payment(
        Order $order,
        string $reference,
    ): Payment {
        return Payment::query()->create([
            'order_id' => $order->getKey(),

            'gateway' => 'zarinpal',

            'amount' => $order->final_amount,
            'currency' => $order->currency,

            'status' => PaymentStatus::Pending,

            'gateway_reference' => $reference,

            'gateway_transaction_id' => null,

            'redirect_url' => 'https://example.test/payment',

            'failure_code' => null,
            'verified_at' => null,
        ]);
    }
}
