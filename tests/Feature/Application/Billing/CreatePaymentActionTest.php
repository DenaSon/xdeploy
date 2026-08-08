<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CreatePaymentAction;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentInitiationData;
use App\Domain\Billing\DTOs\PaymentInitiationRequestData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\OrderNotPayableException;
use App\Domain\Billing\Exceptions\OrderQuoteExpiredException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class CreatePaymentActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_creates_pending_payment_from_order_snapshot(): void
    {
        Carbon::setTestNow('2026-08-08 20:00:00');

        $user = User::factory()->create();

        $order = $this->createOrder(
            user: $user,
            finalAmount: 2_165_760,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldReceive('initiate')
            ->once()
            ->with(Mockery::on(
                static function (
                    PaymentInitiationRequestData $request,
                ) use ($order): bool {
                    return $request->orderId === $order->id
                        && $request->amount === 2_165_760
                        && $request->currency === 'IRR'
                        && $request->callbackUrl === 'https://xdeploy.test/payment/callback';
                },
            ))
            ->andReturn(
                new PaymentInitiationData(
                    reference: 'REF-123',
                    redirectUrl: 'https://gateway.test/pay/REF-123',
                ),
            );

        $action = new CreatePaymentAction(
            gateway: $gateway,
        );

        $result = $action->execute(
            user: $user,
            orderId: $order->id,
            callbackUrl: 'https://xdeploy.test/payment/callback',
        );

        $this->assertSame(
            $order->id,
            $result->orderId,
        );

        $this->assertSame(
            'fake',
            $result->gateway,
        );

        $this->assertSame(
            2_165_760,
            $result->amount,
        );

        $this->assertSame(
            'IRR',
            $result->currency,
        );

        $this->assertSame(
            'REF-123',
            $result->reference,
        );

        $this->assertSame(
            'https://gateway.test/pay/REF-123',
            $result->redirectUrl,
        );

        $payment = Payment::query()
            ->findOrFail($result->paymentId);

        $this->assertSame(
            PaymentStatus::Pending,
            $payment->status,
        );

        $this->assertSame(
            2_165_760,
            $payment->amount,
        );

        $this->assertSame(
            'REF-123',
            $payment->gateway_reference,
        );

        $this->assertNull(
            $payment->gateway_transaction_id,
        );

        $this->assertNull(
            $payment->verified_at,
        );

        /*
         * Starting payment must not change Order state.
         */
        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );

        $this->assertDatabaseHas('payments', [
            'id' => $result->paymentId,
            'order_id' => $order->id,
            'gateway' => 'fake',
            'amount' => 2_165_760,
            'currency' => 'IRR',
            'status' => 'pending',
            'gateway_reference' => 'REF-123',
        ]);
    }

    public function test_it_rejects_an_expired_order_quote(): void
    {
        Carbon::setTestNow('2026-08-08 20:00:00');

        $user = User::factory()->create();

        $order = $this->createOrder(
            user: $user,
            quoteExpiresAt: Carbon::parse(
                '2026-08-08 19:59:00',
            ),
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway->shouldNotReceive('name');
        $gateway->shouldNotReceive('initiate');

        $action = new CreatePaymentAction(
            gateway: $gateway,
        );

        $this->expectException(
            OrderQuoteExpiredException::class,
        );

        try {
            $action->execute(
                user: $user,
                orderId: $order->id,
                callbackUrl: 'https://xdeploy.test/payment/callback',
            );
        } finally {
            $this->assertDatabaseCount(
                'payments',
                0,
            );
        }
    }

    public function test_it_rejects_order_that_is_not_pending_payment(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder(
            user: $user,
            status: OrderStatus::Paid,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway->shouldNotReceive('name');
        $gateway->shouldNotReceive('initiate');

        $action = new CreatePaymentAction(
            gateway: $gateway,
        );

        $this->expectException(
            OrderNotPayableException::class,
        );

        try {
            $action->execute(
                user: $user,
                orderId: $order->id,
                callbackUrl: 'https://xdeploy.test/payment/callback',
            );
        } finally {
            $this->assertDatabaseCount(
                'payments',
                0,
            );
        }
    }

    public function test_it_marks_payment_failed_when_gateway_initiation_fails(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder(
            user: $user,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldReceive('initiate')
            ->once()
            ->andThrow(
                new RuntimeException('Gateway unavailable.'),
            );

        $action = new CreatePaymentAction(
            gateway: $gateway,
        );

        try {
            $action->execute(
                user: $user,
                orderId: $order->id,
                callbackUrl: 'https://xdeploy.test/payment/callback',
            );

            $this->fail(
                'Expected gateway exception was not thrown.',
            );
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Gateway unavailable.',
                $exception->getMessage(),
            );
        }

        $payment = Payment::query()->sole();

        $this->assertSame(
            PaymentStatus::Failed,
            $payment->status,
        );

        $this->assertSame(
            'initiation_failed',
            $payment->failure_code,
        );

        $this->assertNull(
            $payment->gateway_reference,
        );

        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );
    }

    private function createOrder(
        User $user,
        int $finalAmount = 1_781_760,
        OrderStatus $status = OrderStatus::PendingPayment,
        ?Carbon $quoteExpiresAt = null,
    ): Order {
        return Order::query()->create([
            'user_id' => $user->id,

            'region_id' => 'eu-west1-a',
            'size_id' => 'eco-2-2-0',

            'default_disk_gib' => 30,
            'selected_disk_gib' => 30,

            'period' => '2_days',
            'duration_hours' => 48,

            'provider_cost' => 1_113_600,
            'markup_percent' => 60,
            'final_amount' => $finalAmount,

            'currency' => 'IRR',
            'status' => $status,

            'quote_expires_at' => $quoteExpiresAt
                ?? now()->addMinutes(15),

            'paid_at' => null,
        ]);
    }
}
