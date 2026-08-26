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

final class ZarinPalCallbackControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_callback_verifies_payment_then_queues_paid_order_provisioning(): void
    {
        Queue::fake();

        $user =
            User::factory()->create();

        $order =
            $this->createOrder(
                user: $user,
                status: OrderStatus::PendingPayment,
            );

        $payment =
            $this->createPayment(
                order: $order,
                status: PaymentStatus::Pending,
                reference: 'AUTH-QUEUE-123',
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
                    reference: 'AUTH-QUEUE-123',

                    transactionId: 'TX-QUEUE-987',

                    amount: $order->final_amount,

                    verifiedAt: new DateTimeImmutable(
                        '2026-08-08 20:30:00',
                    ),
                ),
            );

        $this->app->instance(
            PaymentGatewayInterface::class,
            $gateway,
        );

        $response = $this->get(
            '/payments/zarinpal/callback'
            .'?Authority=AUTH-QUEUE-123'
            .'&Status=OK',
        );

        $response->assertRedirect(
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
            'TX-QUEUE-987',
            $payment->fresh()
                ->gateway_transaction_id,
        );

        $this->assertSame(
            OrderStatus::Paid,
            $order->fresh()->status,
        );

        Queue::assertPushed(
            ProvisionPaidOrderJob::class,
            function (
                ProvisionPaidOrderJob $job,
            ) use ($order): bool {
                return $job->orderId
                        === $order->id
                    && $job->queue
                        === 'provisioning';
            },
        );
    }

    public function test_repeated_paid_callback_does_not_queue_fulfilled_order_again(): void
    {
        Queue::fake();

        $user =
            User::factory()->create();

        $order =
            $this->createOrder(
                user: $user,
                status: OrderStatus::Fulfilled,
            );

        $payment =
            $this->createPayment(
                order: $order,
                status: PaymentStatus::Paid,
                reference: 'AUTH-FULFILLED',
                transactionId: 'TX-FULFILLED',
                verifiedAt: new DateTimeImmutable(
                    '2026-08-08 20:30:00',
                ),
            );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('zarinpal');

        $gateway
            ->shouldNotReceive(
                'verify',
            );

        $this->app->instance(
            PaymentGatewayInterface::class,
            $gateway,
        );

        $response = $this->get(
            '/payments/zarinpal/callback'
            .'?Authority=AUTH-FULFILLED'
            .'&Status=OK',
        );

        $response->assertRedirect(
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

        Queue::assertNothingPushed();
    }

    public function test_cancelled_callback_never_queues_provisioning(): void
    {
        Queue::fake();

        $user =
            User::factory()->create();

        $order =
            $this->createOrder(
                user: $user,
                status: OrderStatus::PendingPayment,
            );

        $payment =
            $this->createPayment(
                order: $order,
                status: PaymentStatus::Pending,
                reference: 'AUTH-CANCELLED',
            );

        $response = $this->get(
            '/payments/zarinpal/callback'
            .'?Authority=AUTH-CANCELLED'
            .'&Status=NOK',
        );

        $response->assertRedirect(
            route(
                'panel.orders.show',
                $order,
            ),
        );

        $this->assertSame(
            PaymentStatus::Cancelled,
            $payment->fresh()->status,
        );

        /*
         * Cancelling a gateway attempt does not cancel the commercial Order.
         * The customer may start payment again while the quote remains valid.
         */
        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );

        /*
         * Billing notifications are allowed on the notifications queue, but a
         * cancelled gateway callback must never enqueue VPS provisioning.
         */
        Queue::assertNotPushed(
            ProvisionPaidOrderJob::class,
        );
    }

    private function createOrder(
        User $user,
        OrderStatus $status,
    ): Order {
        return Order::query()->create([
            'user_id' => $user->id,

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

            'provider_cost' => 1_113_600,

            'markup_percent' => 60,

            'final_amount' => 1_781_760,

            'currency' => 'IRR',

            'status' => $status,

            'quote_expires_at' => now()->addMinutes(15),

            'paid_at' => $status
                === OrderStatus::PendingPayment
                    ? null
                    : now(),
        ]);
    }

    private function createPayment(
        Order $order,
        PaymentStatus $status,
        string $reference,
        ?string $transactionId = null,
        ?DateTimeImmutable $verifiedAt = null,
    ): Payment {
        return Payment::query()->create([
            'order_id' => $order->id,

            'gateway' => 'zarinpal',

            'amount' => $order->final_amount,

            'currency' => $order->currency,

            'status' => $status,

            'gateway_reference' => $reference,

            'gateway_transaction_id' => $transactionId,

            'redirect_url' => 'https://gateway.test/pay',

            'failure_code' => null,

            'verified_at' => $verifiedAt,
        ]);
    }
}
