<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\CreatedPaymentData;
use App\Domain\Billing\DTOs\PaymentInitiationRequestData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\OrderNotPayableException;
use App\Domain\Billing\Exceptions\OrderQuoteExpiredException;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class CreatePaymentAction
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    public function execute(
        User $user,
        int $orderId,
        string $callbackUrl,
    ): CreatedPaymentData {
        $order = DB::transaction(function () use ($user, $orderId): Order {
            /** @var Order $order */
            $order = Order::query()
                ->whereKey($orderId)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status !== OrderStatus::PendingPayment) {
                throw OrderNotPayableException::forOrder(
                    orderId: $order->getKey(),
                    status: $order->status->value,
                );
            }

            if (
                $order->quote_expires_at !== null
                && $order->quote_expires_at->isPast()
            ) {
                $order->forceFill([
                    'status' => OrderStatus::Expired,
                ])->save();

                throw OrderQuoteExpiredException::forOrder(
                    $order->getKey(),
                );
            }

            return $order;
        });

        $payment = Payment::query()->create([
            'order_id' => $order->getKey(),
            'gateway' => $this->gateway->name(),
            'amount' => $order->final_amount,
            'currency' => $order->currency,
            'status' => PaymentStatus::Initiating,
            'gateway_reference' => null,
            'gateway_transaction_id' => null,
            'redirect_url' => null,
            'failure_code' => null,
            'verified_at' => null,
        ]);

        try {
            $initiation = $this->gateway->initiate(
                new PaymentInitiationRequestData(
                    orderId: $order->getKey(),
                    amount: $payment->amount,
                    currency: $payment->currency,
                    callbackUrl: $callbackUrl,
                    description: "xDeploy order #{$order->getKey()}",
                ),
            );
        } catch (Throwable $exception) {
            $payment->forceFill([
                'status' => PaymentStatus::Failed,
                'failure_code' => 'initiation_failed',
            ])->save();

            throw $exception;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Pending,
            'gateway_reference' => $initiation->reference,
            'redirect_url' => $initiation->redirectUrl,
        ])->save();

        return new CreatedPaymentData(
            paymentId: $payment->getKey(),
            orderId: $order->getKey(),
            gateway: $payment->gateway,
            amount: $payment->amount,
            currency: $payment->currency,
            reference: $initiation->reference,
            redirectUrl: $initiation->redirectUrl,
        );
    }
}
