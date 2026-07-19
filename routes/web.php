<?php

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\Facades\Route;
use App\Domain\Module\Modules\Docker\DockerModule;

Route::prefix('panel')
    ->as('panel.')
    ->group(function () {});

Route::prefix('core')
    ->as('core.')
    ->group(function () {

        Route::livewire('/dashboard', 'pages::core.dashboard.index')
            ->name('dashboard.index');

    });

Route::prefix('panel')
    ->as('panel.')
    ->group(function () {

        Route::livewire('/', 'pages::panel.index')->name('panel');

    });






require __DIR__.'/settings.php';
