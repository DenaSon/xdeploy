<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Application\Billing\Actions\CreatePaymentAction;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class StartPaymentController extends Controller
{
    public function __invoke(
        Request $request,
        Order $order,
        CreatePaymentAction $action,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $payment = $action->execute(
            user: $user,
            orderId: $order->getKey(),
            callbackUrl: route(
                'payments.zarinpal.callback',
            ),
        );

        return redirect()->away(
            $payment->redirectUrl,
        );
    }
}
