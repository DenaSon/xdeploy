<?php

declare(strict_types=1);

use App\Http\Controllers\Applications\AmneziaWg\DeactivatePeerController;
use App\Http\Controllers\Applications\AmneziaWg\DownloadPeerConfigController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('panel')
    ->as('panel.')
    ->group(function (): void {
        Route::get(
            '/servers/{server}/applications/amneziawg/peers/{peer}/config',
            DownloadPeerConfigController::class,
        )->name('servers.applications.amneziawg.peers.config');

        Route::post(
            '/servers/{server}/applications/amneziawg/peers/{peer}/deactivate',
            DeactivatePeerController::class,
        )->name('servers.applications.amneziawg.peers.deactivate');
    });
