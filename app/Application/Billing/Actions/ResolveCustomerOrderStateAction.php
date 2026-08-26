<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Domain\Billing\Enums\CustomerOrderState;
use App\Domain\Billing\Enums\OrderStatus;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

final readonly class ResolveCustomerOrderStateAction
{
    public function execute(Order $order): CustomerOrderState
    {
        return match ($order->status) {
            OrderStatus::PendingPayment => $this->pendingPaymentState(
                $this->latestPayment($order),
            ),

            OrderStatus::Paid,
            OrderStatus::Provisioning => CustomerOrderState::Processing,

            OrderStatus::Fulfilled => CustomerOrderState::Completed,
            OrderStatus::Failed => CustomerOrderState::NeedsAttention,
            OrderStatus::Cancelled => CustomerOrderState::Cancelled,
            OrderStatus::Expired => CustomerOrderState::Expired,
        };
    }

    private function pendingPaymentState(
        ?Payment $payment,
    ): CustomerOrderState {
        if ($payment === null) {
            return CustomerOrderState::AwaitingPayment;
        }

        return match ($payment->status) {
            PaymentStatus::Initiating,
            PaymentStatus::Pending => CustomerOrderState::PaymentPending,

            /*
             * A verified payment can briefly race with the order state update.
             * Never tell the customer to pay again while the latest attempt is
             * already marked paid.
             */
            PaymentStatus::Paid => CustomerOrderState::Processing,

            PaymentStatus::Failed,
            PaymentStatus::Cancelled => CustomerOrderState::AwaitingPayment,
        };
    }

    private function latestPayment(Order $order): ?Payment
    {
        if ($order->relationLoaded('latestPayment')) {
            return $order->latestPayment;
        }

        return $order->latestPayment()->first();
    }
}
