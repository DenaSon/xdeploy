<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Billing\Events\PaymentStatusChanged;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

final readonly class CancelPendingPaymentAction
{
    public function execute(
        string $gateway,
        string $reference,
    ): Payment {
        /** @var array{payment: Payment, changed: bool} $result */
        $result = DB::transaction(function () use (
            $gateway,
            $reference,
        ): array {
            /** @var Payment $payment */
            $payment = Payment::query()
                ->where('gateway', $gateway)
                ->where('gateway_reference', $reference)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $payment->status === PaymentStatus::Cancelled
                || $payment->status === PaymentStatus::Paid
            ) {
                return [
                    'payment' => $payment,
                    'changed' => false,
                ];
            }

            $changed = false;

            if ($payment->status === PaymentStatus::Pending) {
                $payment->forceFill([
                    'status' => PaymentStatus::Cancelled,
                    'failure_code' => 'customer_cancelled',
                ])->save();

                $changed = true;
            }

            return [
                'payment' => $payment->fresh(),
                'changed' => $changed,
            ];
        });

        $payment = $result['payment'];

        if ($result['changed']) {
            try {
                Event::dispatch(new PaymentStatusChanged(
                    paymentId: (int) $payment->getKey(),
                    orderId: $payment->order_id,
                    status: PaymentStatus::Cancelled,
                ));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $payment;
    }
}
