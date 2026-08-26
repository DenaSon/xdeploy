<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Billing\Events\PaymentStatusChanged;
use App\Domain\Billing\Contracts\PaymentGatewayInterface;
use App\Domain\Billing\DTOs\PaymentVerificationRequestData;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Exceptions\PaymentNotVerifiableException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

final readonly class VerifyPaymentAction
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
    ) {}

    public function execute(string $gatewayReference): Payment
    {
        /** @var Payment $payment */
        $payment = Payment::query()
            ->where('gateway', $this->gateway->name())
            ->where('gateway_reference', $gatewayReference)
            ->firstOrFail();

        /*
         * Gateway callbacks may be delivered more than once.
         * A previously verified payment is therefore a successful,
         * idempotent result and must not emit another notification.
         */
        if ($payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        if ($payment->status !== PaymentStatus::Pending) {
            throw PaymentNotVerifiableException::forPayment(
                paymentId: $payment->getKey(),
                status: $payment->status->value,
            );
        }

        $verification = $this->gateway->verify(
            new PaymentVerificationRequestData(
                reference: $payment->gateway_reference,
                amount: $payment->amount,
                currency: $payment->currency,
            ),
        );

        if ($verification->reference !== $payment->gateway_reference) {
            throw PaymentNotVerifiableException::referenceMismatch(
                $payment->getKey(),
            );
        }

        if ($verification->amount !== $payment->amount) {
            throw PaymentNotVerifiableException::amountMismatch(
                $payment->getKey(),
            );
        }

        /**
         * @var array{payment: Payment, changed: bool} $result
         */
        $result = DB::transaction(function () use (
            $payment,
            $verification,
        ): array {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status === PaymentStatus::Paid) {
                return [
                    'payment' => $lockedPayment,
                    'changed' => false,
                ];
            }

            if ($lockedPayment->status !== PaymentStatus::Pending) {
                throw PaymentNotVerifiableException::forPayment(
                    paymentId: $lockedPayment->getKey(),
                    status: $lockedPayment->status->value,
                );
            }

            /** @var Order $order */
            $order = Order::query()
                ->whereKey($lockedPayment->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $order->status !== OrderStatus::PendingPayment
                && $order->status !== OrderStatus::Paid
            ) {
                throw PaymentNotVerifiableException::forPayment(
                    paymentId: $lockedPayment->getKey(),
                    status: $order->status->value,
                );
            }

            $verifiedAt = $verification->verifiedAt;

            $lockedPayment->forceFill([
                'status' => PaymentStatus::Paid,
                'gateway_transaction_id' => $verification->transactionId,
                'verified_at' => $verifiedAt,
                'failure_code' => null,
            ])->save();

            if ($order->status !== OrderStatus::Paid) {
                $order->forceFill([
                    'status' => OrderStatus::Paid,
                    'paid_at' => $verifiedAt,
                ])->save();
            }

            return [
                'payment' => $lockedPayment->fresh(),
                'changed' => true,
            ];
        });

        $verifiedPayment = $result['payment'];

        if ($result['changed']) {
            $this->dispatchStatusChanged(
                $verifiedPayment,
                PaymentStatus::Paid,
            );
        }

        return $verifiedPayment;
    }

    private function dispatchStatusChanged(
        Payment $payment,
        PaymentStatus $status,
    ): void {
        try {
            Event::dispatch(new PaymentStatusChanged(
                paymentId: (int) $payment->getKey(),
                orderId: $payment->order_id,
                status: $status,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
