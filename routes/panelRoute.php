<?php

declare(strict_types=1);

use App\Livewire\Applications\Index as ApplicationsIndex;
use App\Livewire\Applications\Show as ApplicationShow;
use App\Livewire\Servers\Create as ServerCreate;
use App\Livewire\Servers\Dashboard as ServerDashboard;
use App\Livewire\Servers\Edit as ServerEdit;
use App\Livewire\Servers\Index as ServersIndex;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])
    ->group(function (): void {
        Route::livewire(
            '/login',
            'auth.login-page',
        )->name('login');

        Route::livewire(
            '/verify',
            'auth.verify-otp-page',
        )->name('verify');
    });

Route::middleware(['web', 'auth'])
    ->prefix('panel')
    ->as('panel.')
    ->group(function (): void {
        Route::livewire(
            '/servers',
            ServersIndex::class,
        )->name('servers.index');

        Route::livewire(
            '/servers/create',
            ServerCreate::class,
        )->name('servers.create');

        Route::livewire(
            '/servers/{server}/edit',
            ServerEdit::class,
        )->name('servers.edit');

        Route::livewire(
            '/servers/{server}',
            ServerDashboard::class,
        )->name('servers.dashboard');

        Route::livewire(
            '/servers/{server}/applications',
            ApplicationsIndex::class,
        )->name('servers.applications.index');

        Route::livewire(
            '/servers/{server}/applications/{application}',
            ApplicationShow::class,
        )->name('servers.applications.show');
    });
