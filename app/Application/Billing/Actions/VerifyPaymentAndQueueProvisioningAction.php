<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Models\Order;
use App\Models\Payment;

final readonly class VerifyPaymentAndQueueProvisioningAction
{
    public function __construct(
        private VerifyPaymentAction $verifyPayment,
        private FulfillPaidOrderAction $fulfillPaidOrder,
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
         * Commercial fulfillment is selected by Order type:
         * - cloud purchase -> queued provider provisioning
         * - cloud renewal  -> idempotent expiration extension
         *
         * Repeated callbacks are safe because each fulfillment path owns an
         * independent status/idempotency boundary.
         */
        $this->fulfillPaidOrder->execute(
            $order,
        );

        return $payment;
    }
}
