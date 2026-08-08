<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\StartPaymentController;
use App\Http\Controllers\Payment\ZarinPalCallbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Production payment routes
|--------------------------------------------------------------------------
*/

Route::post(
    '/payments/orders/{order}/start',
    StartPaymentController::class,
)
    ->middleware('auth')
    ->name('payments.start');

Route::get(
    '/payments/zarinpal/callback',
    ZarinPalCallbackController::class,
)
    ->name('payments.zarinpal.callback');

/*
|--------------------------------------------------------------------------
| Local browser test route
|--------------------------------------------------------------------------
|
| Example:
|
| http://localhost:8000/debug/payments/orders/5/start
|
| This performs a real/sandbox ZarinPal payment initiation depending on
| ZARINPAL_SANDBOX and redirects the browser to the gateway.
|
*/

if (app()->isLocal()) {
    Route::get(
        '/debug/payments/orders/{order}/start',
        StartPaymentController::class,
    )
        ->middleware('auth')
        ->name('debug.payments.start');
}
