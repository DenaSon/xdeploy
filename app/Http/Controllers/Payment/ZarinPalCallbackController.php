<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Application\Billing\Actions\CancelPendingPaymentAction;
use App\Application\Billing\Actions\VerifyPaymentAndFulfillOrderAction;
use App\Http\Controllers\Controller;
use App\Infrastructure\Payment\ZarinPal\ZarinPalPaymentException;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ZarinPalCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        VerifyPaymentAndFulfillOrderAction $verifyAndFulfill,
        CancelPendingPaymentAction $cancel,
    ): JsonResponse|RedirectResponse {
        $authority = trim(
            (string) $request->query(
                'Authority',
                '',
            ),
        );

        $status = strtoupper(
            trim(
                (string) $request->query(
                    'Status',
                    '',
                ),
            ),
        );

        /*
         * Without an Authority there is no safe way to correlate the callback
         * with an Order. Keep the explicit validation response instead of
         * guessing a customer destination.
         */
        if ($authority === '') {
            return response()->json([
                'success' => false,
                'message' => 'Missing payment authority.',
            ], 422);
        }

        /*
         * Status is only a redirect hint from the customer journey.
         * It is never treated as proof of payment.
         */
        if ($status !== 'OK') {
            $payment = $cancel->execute(
                gateway: 'zarinpal',
                reference: $authority,
            );

            return $this->redirectAfterPayment(
                payment: $payment,
                result: 'cancelled',
            );
        }

        /*
         * Financial verification is committed first. The order type then
         * selects its fulfillment strategy: asynchronous provider creation
         * for provisioning or immediate DB-only extension for renewal.
         */
        try {
            $payment = $verifyAndFulfill->execute(
                gatewayReference: $authority,
            );
        } catch (ZarinPalPaymentException $exception) {
            if (! $exception->isRetryable()) {
                throw $exception;
            }

            $payment = Payment::query()
                ->where('gateway', 'zarinpal')
                ->where('gateway_reference', $authority)
                ->first();

            if (! $payment instanceof Payment) {
                throw $exception;
            }

            Log::warning(
                'Payment verification temporarily unavailable.',
                [
                    'gateway' => 'zarinpal',
                    'payment_id' => $payment->getKey(),
                    'order_id' => $payment->order_id,
                    'code' => $exception->getCode(),
                ],
            );

            return $this
                ->redirectAfterPayment(
                    payment: $payment,
                )
                ->with(
                    'payment_verification_pending',
                    true,
                )
                ->with(
                    'payment_verification_retry_url',
                    route(
                        'payments.zarinpal.callback',
                        [
                            'Authority' => $authority,
                            'Status' => 'OK',
                        ],
                    ),
                );
        }

        return $this->redirectAfterPayment(
            payment: $payment,
            result: 'success',
        );
    }

    private function redirectAfterPayment(
        Payment $payment,
        ?string $result = null,
    ): RedirectResponse {
        /** @var Order $order */
        $order = $payment->order()
            ->firstOrFail();

        if (
            $order->isRenewal()
            && $order->server_id !== null
        ) {
            $parameters = [
                'server' => $order->server_id,
            ];

            if ($result !== null) {
                $parameters['payment'] = $result;
            }

            return redirect()->route(
                'panel.servers.renew',
                $parameters,
            );
        }

        return redirect()->route(
            'panel.orders.show',
            [
                'order' => $order->getKey(),
            ],
        );
    }
}
