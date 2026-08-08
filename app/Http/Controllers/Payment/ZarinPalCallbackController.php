<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Application\Billing\Actions\CancelPendingPaymentAction;
use App\Application\Billing\Actions\VerifyPaymentAndQueueProvisioningAction;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ZarinPalCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        VerifyPaymentAndQueueProvisioningAction $verifyAndQueue,
        CancelPendingPaymentAction $cancel,
    ): JsonResponse {
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

            return response()->json([
                'success' => false,

                'payment_id' => $payment->getKey(),

                'order_id' => $payment->order_id,

                'status' => $payment->status->value,

                'message' => 'Payment was cancelled or not completed.',
            ]);
        }

        /*
         * Financial verification is completed and committed first.
         * Only then is asynchronous cloud fulfillment queued.
         */
        $payment = $verifyAndQueue->execute(
            gatewayReference: $authority,
        );

        return response()->json([
            'success' => true,

            'payment_id' => $payment->getKey(),

            'order_id' => $payment->order_id,

            'status' => PaymentStatus::Paid->value,

            'transaction_id' => $payment->gateway_transaction_id,

            'verified_at' => $payment->verified_at
                ?->toIso8601String(),
        ]);
    }
}
