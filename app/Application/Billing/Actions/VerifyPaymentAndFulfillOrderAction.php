<?php

declare(strict_types=1);

namespace App\Application\Billing\Actions;

use App\Application\Billing\Jobs\ProvisionPaidOrderJob;
use App\Domain\Billing\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use LogicException;

final readonly class VerifyPaymentAndFulfillOrderAction
{
    public function __construct(
        private VerifyPaymentAction $verifyPayment,
        private FulfillPaidRenewalOrderAction $fulfillRenewal,
    ) {}

    public function execute(
        string $gatewayReference,
    ): Payment {
        /*
         * VerifyPaymentAction owns and commits the financial transaction.
         * Fulfillment starts only after the Payment + Order are durable.
         */
        $payment = $this->verifyPayment->execute(
            gatewayReference: $gatewayReference,
        );

        /** @var Order $order */
        $order = $payment->order()
            ->firstOrFail();

        /*
         * Repeated gateway callbacks after fulfillment/provisioning has
         * already advanced are successful idempotent payment callbacks.
         */
        if ($order->status !== OrderStatus::Paid) {
            return $payment;
        }

        if ($order->isProvisioning()) {
            /*
             * Provider creation is a billable external side effect and keeps
             * its existing asynchronous/unique provisioning boundary.
             */
            ProvisionPaidOrderJob::dispatch(
                $order->getKey(),
            );

            return $payment;
        }

        if ($order->isRenewal()) {
            /*
             * Renewal is a short database-only state transition. Execute it
             * immediately after verification to minimize the expiry/termination
             * race instead of delaying it behind the provisioning queue.
             */
            $this->fulfillRenewal->execute(
                (int) $order->getKey(),
            );

            return $payment;
        }

        throw new LogicException(
            sprintf(
                'Order [%d] has an unsupported fulfillment type.',
                $order->getKey(),
            ),
        );
    }
}
