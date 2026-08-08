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
use App\Domain\Billing\Exceptions\PaymentInitiationInProgressException;
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

    public function test_it_reserves_initiating_payment_before_calling_gateway_and_finishes_as_pending(): void
    {
        Carbon::setTestNow(
            '2026-08-08 20:00:00',
        );

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
            ->with(
                Mockery::on(
                    static function (
                        PaymentInitiationRequestData $request,
                    ) use ($order): bool {
                        return $request->orderId
                                === $order->id
                            && $request->amount
                                === 2_165_760
                            && $request->currency
                                === 'IRR'
                            && $request->callbackUrl
                                === 'https://xdeploy.test/payment/callback';
                    },
                ),
            )
            ->andReturnUsing(
                function () use (
                    $order,
                ): PaymentInitiationData {
                    /*
                     * The reservation must already be visible before the
                     * external gateway call starts.
                     */
                    $reserved =
                        Payment::query()
                            ->where(
                                'order_id',
                                $order->id,
                            )
                            ->sole();

                    $this->assertSame(
                        PaymentStatus::Initiating,
                        $reserved->status,
                    );

                    $this->assertNull(
                        $reserved->gateway_reference,
                    );

                    return new PaymentInitiationData(
                        reference: 'REF-123',

                        redirectUrl: 'https://gateway.test/pay/REF-123',
                    );
                },
            );

        $result = $this->action(
            $gateway,
        )->execute(
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

        $payment =
            Payment::query()
                ->findOrFail(
                    $result->paymentId,
                );

        $this->assertSame(
            PaymentStatus::Pending,
            $payment->status,
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

        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );

        $this->assertDatabaseCount(
            'payments',
            1,
        );
    }

    public function test_it_reuses_existing_pending_payment_instead_of_creating_another_gateway_authority(): void
    {
        $user =
            User::factory()->create();

        $order = $this->createOrder(
            user: $user,
        );

        $existing =
            $this->createPayment(
                order: $order,

                status: PaymentStatus::Pending,

                reference: 'REF-EXISTING',

                redirectUrl: 'https://gateway.test/pay/REF-EXISTING',
            );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldNotReceive(
                'initiate',
            );

        $result = $this->action(
            $gateway,
        )->execute(
            user: $user,
            orderId: $order->id,
            callbackUrl: 'https://xdeploy.test/payment/callback',
        );

        $this->assertSame(
            $existing->id,
            $result->paymentId,
        );

        $this->assertSame(
            'REF-EXISTING',
            $result->reference,
        );

        $this->assertSame(
            'https://gateway.test/pay/REF-EXISTING',
            $result->redirectUrl,
        );

        $this->assertDatabaseCount(
            'payments',
            1,
        );
    }

    public function test_it_blocks_second_payment_attempt_while_an_initiation_is_already_in_progress(): void
    {
        $user =
            User::factory()->create();

        $order = $this->createOrder(
            user: $user,
        );

        $this->createPayment(
            order: $order,
            status: PaymentStatus::Initiating,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldNotReceive(
                'initiate',
            );

        $this->expectException(
            PaymentInitiationInProgressException::class,
        );

        try {
            $this->action(
                $gateway,
            )->execute(
                user: $user,
                orderId: $order->id,
                callbackUrl: 'https://xdeploy.test/payment/callback',
            );
        } finally {
            $this->assertDatabaseCount(
                'payments',
                1,
            );
        }
    }

    public function test_it_persists_expired_order_status_before_throwing_quote_expired_exception(): void
    {
        Carbon::setTestNow(
            '2026-08-08 20:00:00',
        );

        $user =
            User::factory()->create();

        $order = $this->createOrder(
            user: $user,

            quoteExpiresAt: Carbon::parse(
                '2026-08-08 19:59:00',
            ),
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldNotReceive(
                'initiate',
            );

        $this->expectException(
            OrderQuoteExpiredException::class,
        );

        try {
            $this->action(
                $gateway,
            )->execute(
                user: $user,
                orderId: $order->id,
                callbackUrl: 'https://xdeploy.test/payment/callback',
            );
        } finally {
            $this->assertSame(
                OrderStatus::Expired,
                $order->fresh()->status,
            );

            $this->assertDatabaseCount(
                'payments',
                0,
            );
        }
    }

    public function test_expired_quote_does_not_invalidate_payment_already_initiated_while_order_was_payable(): void
    {
        Carbon::setTestNow(
            '2026-08-08 20:00:00',
        );

        $user =
            User::factory()->create();

        $order = $this->createOrder(
            user: $user,

            quoteExpiresAt: Carbon::parse(
                '2026-08-08 19:59:00',
            ),
        );

        $existing =
            $this->createPayment(
                order: $order,

                status: PaymentStatus::Pending,

                reference: 'REF-BEFORE-EXPIRY',

                redirectUrl: 'https://gateway.test/pay/REF-BEFORE-EXPIRY',
            );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->once()
            ->andReturn('fake');

        $gateway
            ->shouldNotReceive(
                'initiate',
            );

        $result = $this->action(
            $gateway,
        )->execute(
            user: $user,
            orderId: $order->id,
            callbackUrl: 'https://xdeploy.test/payment/callback',
        );

        $this->assertSame(
            $existing->id,
            $result->paymentId,
        );

        $this->assertSame(
            OrderStatus::PendingPayment,
            $order->fresh()->status,
        );

        $this->assertDatabaseCount(
            'payments',
            1,
        );
    }

    public function test_it_rejects_order_that_is_not_pending_payment(): void
    {
        $user =
            User::factory()->create();

        $order = $this->createOrder(
            user: $user,
            status: OrderStatus::Paid,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldNotReceive(
                'name',
            );

        $gateway
            ->shouldNotReceive(
                'initiate',
            );

        $this->expectException(
            OrderNotPayableException::class,
        );

        try {
            $this->action(
                $gateway,
            )->execute(
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

    public function test_gateway_failure_marks_reserved_payment_failed_and_allows_a_new_attempt(): void
    {
        $user =
            User::factory()->create();

        $order = $this->createOrder(
            user: $user,
        );

        $gateway = Mockery::mock(
            PaymentGatewayInterface::class,
        );

        $gateway
            ->shouldReceive('name')
            ->twice()
            ->andReturn('fake');

        $attempt = 0;

        $gateway
            ->shouldReceive('initiate')
            ->twice()
            ->andReturnUsing(
                static function () use (
                    &$attempt,
                ): PaymentInitiationData {
                    $attempt++;

                    if ($attempt === 1) {
                        throw new RuntimeException(
                            'Gateway unavailable.',
                        );
                    }

                    return new PaymentInitiationData(
                        reference: 'REF-RETRY',

                        redirectUrl: 'https://gateway.test/pay/REF-RETRY',
                    );
                },
            );

        $action = $this->action(
            $gateway,
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

        $failed =
            Payment::query()
                ->orderBy('id')
                ->firstOrFail();

        $this->assertSame(
            PaymentStatus::Failed,
            $failed->status,
        );

        $this->assertSame(
            'initiation_failed',
            $failed->failure_code,
        );

        $result = $action->execute(
            user: $user,
            orderId: $order->id,
            callbackUrl: 'https://xdeploy.test/payment/callback',
        );

        $pending =
            Payment::query()
                ->findOrFail(
                    $result->paymentId,
                );

        $this->assertNotSame(
            $failed->id,
            $pending->id,
        );

        $this->assertSame(
            PaymentStatus::Pending,
            $pending->status,
        );

        $this->assertSame(
            'REF-RETRY',
            $pending->gateway_reference,
        );

        $this->assertDatabaseCount(
            'payments',
            2,
        );
    }

    private function action(
        PaymentGatewayInterface $gateway,
    ): CreatePaymentAction {
        return new CreatePaymentAction(
            gateway: $gateway,
        );
    }

    private function createOrder(
        User $user,
        int $finalAmount = 1_781_760,
        OrderStatus $status =
            OrderStatus::PendingPayment,
        ?Carbon $quoteExpiresAt = null,
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

            'final_amount' => $finalAmount,

            'currency' => 'IRR',
            'status' => $status,

            'quote_expires_at' => $quoteExpiresAt
                ?? now()->addMinutes(15),

            'paid_at' => null,
        ]);
    }

    private function createPayment(
        Order $order,
        PaymentStatus $status,
        ?string $reference = null,
        ?string $redirectUrl = null,
    ): Payment {
        return Payment::query()->create([
            'order_id' => $order->id,

            'gateway' => 'fake',

            'amount' => $order->final_amount,

            'currency' => $order->currency,

            'status' => $status,

            'gateway_reference' => $reference,

            'gateway_transaction_id' => null,

            'redirect_url' => $redirectUrl,

            'failure_code' => null,

            'verified_at' => null,
        ]);
    }
}
