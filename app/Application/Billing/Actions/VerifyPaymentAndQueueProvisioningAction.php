<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;

final readonly class VerifyPaymentAndQueueProvisioningAction
{
    public function __construct(
        private VerifyPaymentAction $verifyPayment,
    ) {}

    public function execute(
        string $gatewayReference,
    ): Payment {
        /*
         * VerifyPaymentAction owns the financial transaction.
         * It returns only after Payment + Order state has committed.
         */
        $payment = $this->verifyPayment->execute(
            gatewayReference: $gatewayReference,
        );

        /** @var Order $order */
        $order = $payment->order()
            ->firstOrFail();

        /*
         * Only a freshly payable Order should enter the fulfillment queue.
         *
         * Repeated gateway callbacks after provisioning has started or
         * completed are successful/idempotent payment callbacks, but they
         * must not enqueue another infrastructure workflow.
         */
        if (
            $order->status
            !== OrderStatus::Paid
        ) {
            return $payment;
        }

        /*
         * At this point the payment transaction is already committed.
         * The queued job therefore observes OrderStatus::Paid.
         *
         * ShouldBeUnique prevents duplicate queued/running jobs for the
         * same Order. ProvisionPaidOrderAction independently protects the
         * Paid -> Provisioning transition and provider creation as the
         * authoritative second safety boundary.
         */
        ProvisionPaidOrderJob::dispatch(
            $order->getKey(),
        );

        return $payment;
    }
}
