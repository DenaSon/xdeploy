<?php

use App\Domain\Dashboard\Services\DashboardService;
use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use Illuminate\Support\Facades\Route;

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



Route::get('/test', function (
    SSHConnectionInterface $ssh,
) {
    $ssh->connect(
        host: '109.122.247.89',
        port: 22,
        username: 'root',
        authenticationType: 'password',
        credential: '000000',
    );

    dd([
        'php' => $ssh->executeWithResult('php --version'),
        'docker' => $ssh->executeWithResult('docker --version'),
        'fake' => $ssh->executeWithResult('command_that_does_not_exist'),
    ]);
});



require __DIR__.'/settings.php';
