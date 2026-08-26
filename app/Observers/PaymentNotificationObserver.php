<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Billing\Events\PaymentStatusChanged;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\Event;
use Throwable;

final class PaymentNotificationObserver
{
    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged('status')) {
            return;
        }

        $status = $payment->status;

        if (! in_array(
            $status,
            [
                PaymentStatus::Paid,
                PaymentStatus::Cancelled,
                PaymentStatus::Failed,
            ],
            true,
        )) {
            return;
        }

        try {
            Event::dispatch(new PaymentStatusChanged(
                paymentId: (int) $payment->getKey(),
                orderId: $payment->order_id,
                status: $status,
            ));
        } catch (Throwable $exception) {
            /*
             * Notification infrastructure must never turn a durable payment
             * transition into a customer-facing payment failure.
             */
            report($exception);
        }
    }
}
