<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Core Routes
|--------------------------------------------------------------------------
|
| Super Admin / System Management
|
*/

Route::middleware([
    'web',
    // 'auth',
])
    ->prefix('core')
    ->as('core.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::livewire('/dashboard', 'core.dashboard')
            ->name('dashboard');

    });
