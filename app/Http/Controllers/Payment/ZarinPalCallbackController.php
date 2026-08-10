<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Application\Billing\Actions\CancelPendingPaymentAction;
use App\Application\Billing\Actions\VerifyPaymentAndQueueProvisioningAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ZarinPalCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        VerifyPaymentAndQueueProvisioningAction $verifyAndQueue,
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

            return redirect()->route(
                'panel.orders.show',
                [
                    'order' => $payment->order_id,
                ],
            );
        }

        /*
         * Financial verification is completed and committed first.
         * Only then is asynchronous cloud fulfillment queued.
         */
        $payment = $verifyAndQueue->execute(
            gatewayReference: $authority,
        );

        return redirect()->route(
            'panel.orders.show',
            [
                'order' => $payment->order_id,
            ],
        );
    }
}
