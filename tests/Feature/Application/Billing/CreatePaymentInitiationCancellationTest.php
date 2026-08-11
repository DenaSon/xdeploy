<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Billing;

use App\Application\Billing\Actions\CreatePaymentAction;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentInitiationData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\OrderType;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\PaymentInitiationCancelledException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class CreatePaymentInitiationCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelled_initiation_is_not_resurrected_as_pending_after_gateway_returns(): void
    {
        $user = User::factory()->create();

        $order = $this->order(
            $user,
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
            ->andReturnUsing(
                function () use ($order): PaymentInitiationData {
                    /** @var Payment $payment */
                    $payment = Payment::query()
                        ->where('order_id', $order->getKey())
                        ->sole();

                    $payment->forceFill([
                        'status' => PaymentStatus::Cancelled,
                        'failure_code' => 'renewal_payment_window_expired',
                    ])->saveOrFail();

                    return new PaymentInitiationData(
                        reference: 'REF-LATE',
                        redirectUrl: 'https://gateway.test/pay/REF-LATE',
                    );
                },
            );

        $this->expectException(
            PaymentInitiationCancelledException::class,
        );

        try {
            (new CreatePaymentAction(
                gateway: $gateway,
            ))->execute(
                user: $user,
                orderId: $order->getKey(),
                callbackUrl: 'https://xdeploy.test/payment/callback',
            );
        } finally {
            $payment = Payment::query()
                ->where('order_id', $order->getKey())
                ->sole();

            $this->assertSame(
                PaymentStatus::Cancelled,
                $payment->status,
            );

            $this->assertSame(
                'renewal_payment_window_expired',
                $payment->failure_code,
            );

            $this->assertNull(
                $payment->gateway_reference,
            );
        }
    }

    private function order(User $user): Order
    {
        return Order::query()->create([
            'type' => OrderType::CloudRenewal,
            'user_id' => $user->getKey(),
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
            'provider_cost' => 48_000,
            'markup_percent' => 75,
            'final_amount' => 84_000,
            'currency' => 'IRR',
            'status' => OrderStatus::PendingPayment,
            'quote_expires_at' => now()->addMinutes(15),
            'paid_at' => null,
        ]);
    }
}
