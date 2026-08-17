<?php

declare(strict_types=1);

use App\Http\Controllers\Integrations\CloudflareConnectionController;
use App\Livewire\Integrations\Cloudflare\Overview as CloudflareOverview;
use App\Livewire\Integrations\Index as IntegrationsIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('panel')
    ->as('panel.')
    ->group(function (): void {
        Route::livewire(
            '/integrations',
            IntegrationsIndex::class,
        )->name('integrations.index');

        Route::livewire(
            '/integrations/cloudflare',
            CloudflareOverview::class,
        )->name('integrations.cloudflare.overview');

        Route::get(
            '/integrations/cloudflare/connect',
            [CloudflareConnectionController::class, 'redirect'],
        )
            ->middleware('throttle:10,1')
            ->name('integrations.cloudflare.redirect');

        Route::get(
            '/integrations/cloudflare/callback',
            [CloudflareConnectionController::class, 'callback'],
        )
            ->middleware('throttle:20,1')
            ->name('integrations.cloudflare.callback');

        Route::delete(
            '/integrations/cloudflare',
            [CloudflareConnectionController::class, 'disconnect'],
        )
            ->middleware('throttle:10,1')
            ->name('integrations.cloudflare.disconnect');
    });
