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
use App\Domain\Billing\Exceptions\PaymentInitiationInProgressException;
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
        $prepared = $this->prepare(
            user: $user,
            orderId: $orderId,
        );

        /*
         * The expired state is persisted inside the transaction and the
         * exception is deliberately thrown only after that transaction
         * commits. Throwing from inside the transaction would roll back
         * the Order status change.
         */
        if ($prepared['expired']) {
            throw OrderQuoteExpiredException::forOrder(
                $orderId,
            );
        }

        $order = $prepared['order'];
        $payment = $prepared['payment'];

        if (
            ! $order instanceof Order
            || ! $payment instanceof Payment
        ) {
            throw new \LogicException(
                'Payment preparation did not produce a valid Order and Payment.',
            );
        }

        /*
         * A valid pending gateway payment is reusable.
         * Do not create a second authority/reference for the same Order.
         */
        if ($prepared['reused']) {
            return $this->createdPaymentData(
                $payment,
            );
        }

        /*
         * The Initiating row was created while the Order was locked and
         * before this external gateway call. Concurrent requests therefore
         * observe an active initiation instead of creating another one.
         */
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

            'failure_code' => null,
        ])->save();

        return $this->createdPaymentData(
            $payment->fresh(),
        );
    }

    /**
     * @return array{
     *     order: Order|null,
     *     payment: Payment|null,
     *     reused: bool,
     *     expired: bool
     * }
     */
    private function prepare(
        User $user,
        int $orderId,
    ): array {
        return DB::transaction(
            function () use (
                $user,
                $orderId,
            ): array {
                /** @var Order $order */
                $order = Order::query()
                    ->whereKey(
                        $orderId,
                    )
                    ->where(
                        'user_id',
                        $user->getKey(),
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $order->status
                    !== OrderStatus::PendingPayment
                ) {
                    throw OrderNotPayableException::forOrder(
                        orderId: $order->getKey(),

                        status: $order->status->value,
                    );
                }

                $gatewayName =
                    $this->gateway->name();

                /*
                 * Existing active payment comes before quote-expiry handling.
                 *
                 * If the gateway authority was already created while the quote
                 * was valid, a later request must not poison that payment by
                 * expiring the Order merely because time has since passed.
                 */
                $activePayment =
                    Payment::query()
                        ->where(
                            'order_id',
                            $order->getKey(),
                        )
                        ->where(
                            'gateway',
                            $gatewayName,
                        )
                        ->whereIn(
                            'status',
                            [
                                PaymentStatus::Initiating->value,
                                PaymentStatus::Pending->value,
                            ],
                        )
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                if (
                    $activePayment
                    instanceof Payment
                ) {
                    if (
                        $activePayment->status
                        === PaymentStatus::Pending
                        && $this->isReusablePendingPayment(
                            $activePayment,
                        )
                    ) {
                        return [
                            'order' => $order,
                            'payment' => $activePayment,
                            'reused' => true,
                            'expired' => false,
                        ];
                    }

                    /*
                     * Initiating means another request has already reserved
                     * this Order for a gateway initiation. We intentionally
                     * refuse to create a second external payment attempt.
                     *
                     * A malformed Pending row is treated just as cautiously.
                     */
                    throw PaymentInitiationInProgressException::forOrder(
                        $order->getKey(),
                    );
                }

                if (
                    $order->quote_expires_at
                    !== null
                    && $order->quote_expires_at
                        ->isPast()
                ) {
                    $order->forceFill([
                        'status' => OrderStatus::Expired,
                    ])->save();

                    return [
                        'order' => null,
                        'payment' => null,
                        'reused' => false,
                        'expired' => true,
                    ];
                }

                /*
                 * Reserve the Order before leaving the database transaction.
                 * This closes the duplicate-initiation race:
                 *
                 * Request A: creates Initiating row, commits, calls gateway.
                 * Request B: waits for Order lock, sees Initiating, stops.
                 */
                $payment =
                    Payment::query()->create([
                        'order_id' => $order->getKey(),

                        'gateway' => $gatewayName,

                        'amount' => $order->final_amount,

                        'currency' => $order->currency,

                        'status' => PaymentStatus::Initiating,

                        'gateway_reference' => null,

                        'gateway_transaction_id' => null,

                        'redirect_url' => null,

                        'failure_code' => null,

                        'verified_at' => null,
                    ]);

                return [
                    'order' => $order,
                    'payment' => $payment,
                    'reused' => false,
                    'expired' => false,
                ];
            },
        );
    }

    private function isReusablePendingPayment(
        Payment $payment,
    ): bool {
        return is_string(
            $payment->gateway_reference,
        )
            && trim(
                $payment->gateway_reference,
            ) !== ''
            && is_string(
                $payment->redirect_url,
            )
            && trim(
                $payment->redirect_url,
            ) !== '';
    }

    private function createdPaymentData(
        Payment $payment,
    ): CreatedPaymentData {
        if (
            ! $this->isReusablePendingPayment(
                $payment,
            )
        ) {
            throw PaymentInitiationInProgressException::forOrder(
                $payment->order_id,
            );
        }

        return new CreatedPaymentData(
            paymentId: $payment->getKey(),

            orderId: $payment->order_id,

            gateway: $payment->gateway,

            amount: $payment->amount,

            currency: $payment->currency,

            reference: $payment->gateway_reference,

            redirectUrl: $payment->redirect_url,
        );
    }
}
