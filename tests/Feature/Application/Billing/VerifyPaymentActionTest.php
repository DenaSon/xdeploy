<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\VerifyPaymentAction;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentVerificationData;
use App\Domain\Billing\DTOs\PaymentVerificationRequestData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\PaymentNotVerifiableException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class VerifyPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_payment_and_order_paid_after_successful_verification(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder($user);

        $payment = $this->createPayment(
            order: $order,
            reference: 'REF-123',
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->with(Mockery::on(
                static function (
                    PaymentVerificationRequestData $request,
                ): bool {
                    return $request->reference === 'REF-123'
                        && $request->amount === 1_781_760
                        && $request->currency === 'IRR';
                },
            ))
            ->andReturn(
                new PaymentVerificationData(
                    reference: 'REF-123',
                    transactionId: 'TX-987654',
                    amount: 1_781_760,
                    verifiedAt: new DateTimeImmutable(
                        '2026-08-08 20:30:00',
                    ),
                ),
            );

        $action = new VerifyPaymentAction(
            gateway: $gateway,
        );

        $result = $action->execute(
            gatewayReference: 'REF-123',
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $result->status,
        );

        $this->assertSame(
            'TX-987654',
            $result->gateway_transaction_id,
        );

        $this->assertSame(
            '2026-08-08 20:30:00',
            $result->verified_at?->format(
                'Y-m-d H:i:s',
            ),
        );

        $freshOrder = $order->fresh();

        $this->assertSame(
            OrderStatus::Paid,
            $freshOrder->status,
        );

        $this->assertSame(
            '2026-08-08 20:30:00',
            $freshOrder->paid_at?->format(
                'Y-m-d H:i:s',
            ),
        );

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'gateway_transaction_id' => 'TX-987654',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_successful_verification_is_idempotent(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder($user);

        $this->createPayment(
            order: $order,
            reference: 'REF-IDEMPOTENT',
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->twice()
            ->andReturn('fake');

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn(
                new PaymentVerificationData(
                    reference: 'REF-IDEMPOTENT',
                    transactionId: 'TX-ONE',
                    amount: 1_781_760,
                    verifiedAt: new DateTimeImmutable(
                        '2026-08-08 20:30:00',
                    ),
                ),
            );

        $action = new VerifyPaymentAction(
            gateway: $gateway,
        );

        $first = $action->execute(
            gatewayReference: 'REF-IDEMPOTENT',
        );

        $second = $action->execute(
            gatewayReference: 'REF-IDEMPOTENT',
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $first->status,
        );

        $this->assertSame(
            PaymentStatus::Paid,
            $second->status,
        );

        $this->assertSame(
            $first->id,
            $second->id,
        );

        $this->assertSame(
            'TX-ONE',
            $second->gateway_transaction_id,
        );

        $this->assertSame(
            OrderStatus::Paid,
            $order->fresh()->status,
        );
    }

    public function test_it_rejects_verified_amount_mismatch(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder($user);

        $payment = $this->createPayment(
            order: $order,
            reference: 'REF-AMOUNT',
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn(
                new PaymentVerificationData(
                    reference: 'REF-AMOUNT',
                    transactionId: 'TX-BAD-AMOUNT',
                    amount: 1,
                    verifiedAt: new DateTimeImmutable,
                ),
            );

        $action = new VerifyPaymentAction(
            gateway: $gateway,
        );

        $this->expectException(
            PaymentNotVerifiableException::class,
        );

        try {
            $action->execute(
                gatewayReference: 'REF-AMOUNT',
            );
        } finally {
            $this->assertSame(
                PaymentStatus::Pending,
                $payment->fresh()->status,
            );

            $this->assertSame(
                OrderStatus::PendingPayment,
                $order->fresh()->status,
            );
        }
    }

    public function test_it_rejects_verified_reference_mismatch(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder($user);

        $payment = $this->createPayment(
            order: $order,
            reference: 'REF-CORRECT',
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldReceive('verify')
            ->once()
            ->andReturn(
                new PaymentVerificationData(
                    reference: 'REF-WRONG',
                    transactionId: 'TX-WRONG',
                    amount: 1_781_760,
                    verifiedAt: new DateTimeImmutable,
                ),
            );

        $action = new VerifyPaymentAction(
            gateway: $gateway,
        );

        $this->expectException(
            PaymentNotVerifiableException::class,
        );

        try {
            $action->execute(
                gatewayReference: 'REF-CORRECT',
            );
        } finally {
            $this->assertSame(
                PaymentStatus::Pending,
                $payment->fresh()->status,
            );

            $this->assertSame(
                OrderStatus::PendingPayment,
                $order->fresh()->status,
            );
        }
    }

    public function test_it_rejects_payment_that_is_not_pending(): void
    {
        $user = User::factory()->create();

        $order = $this->createOrder($user);

        $payment = $this->createPayment(
            order: $order,
            reference: 'REF-FAILED',
            status: PaymentStatus::Failed,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldNotReceive('verify');

        $action = new VerifyPaymentAction(
            gateway: $gateway,
        );

        $this->expectException(
            PaymentNotVerifiableException::class,
        );

        try {
            $action->execute(
                gatewayReference: 'REF-FAILED',
            );
        } finally {
            $this->assertSame(
                PaymentStatus::Failed,
                $payment->fresh()->status,
            );

            $this->assertSame(
                OrderStatus::PendingPayment,
                $order->fresh()->status,
            );
        }
    }

    private function createOrder(
        User $user,
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

            'status' => OrderStatus::PendingPayment,

            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => null,
        ]);
    }

    private function createPayment(
        Order $order,
        string $reference,
        PaymentStatus $status = PaymentStatus::Pending,
    ): Payment {
        return Payment::query()->create([
            'order_id' => $order->id,

            'gateway' => 'fake',

            'amount' => 1_781_760,
            'currency' => 'IRR',

            'status' => $status,

            'gateway_reference' => $reference,
            'gateway_transaction_id' => null,

            'redirect_url' => 'https://gateway.test/pay',

            'failure_code' => null,
            'verified_at' => null,
        ]);
    }
}
