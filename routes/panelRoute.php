<?php

declare(strict_types=1);

use App\Livewire\Servers\Dashboard;
use App\Livewire\Servers\Create;
use App\Livewire\Servers\Edit;
use App\Livewire\Servers\Index;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {

    Route::livewire('/login', 'auth.login-page')
        ->name('login');

});

Route::livewire('/verify/{phone}', 'auth.verify-otp-page')
    ->name('verify');

Route::middleware(['web', 'auth'])
    ->prefix('panel')
    ->as('panel.')
    ->group(function () {

        Route::livewire('/modules', 'modules.index')
            ->name('modules.index');

        Route::livewire('/servers', Index::class)
            ->name('servers.index');

        Route::get('/servers/create', Create::class)
            ->name('servers.create');

        Route::get('/servers/{server}/edit', Edit::class)
            ->name('servers.edit');

        Route::livewire('/servers/{server}', Dashboard::class)
            ->name('servers.dashboard');

    });
