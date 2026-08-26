<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

final readonly class CancelPendingPaymentAction
{
    public function execute(
        string $gateway,
        string $reference,
    ): Payment {
        return DB::transaction(function () use (
            $gateway,
            $reference,
        ): Payment {
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
                return $payment;
            }

            if ($payment->status === PaymentStatus::Pending) {
                $payment->forceFill([
                    'status' => PaymentStatus::Cancelled,
                    'failure_code' => 'customer_cancelled',
                ])->save();
            }

            return $payment->fresh();
        });
    }
}
