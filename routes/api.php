<?php

declare(strict_types=1);

use App\Http\Controllers\Integrations\TelegramWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post(
    '/integrations/telegram/webhook',
    TelegramWebhookController::class,
)
    ->middleware('throttle:120,1')
    ->name('integrations.telegram.webhook');
