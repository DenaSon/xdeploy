<?php

use App\Domain\Dashboard\Services\DashboardService;
use App\Domain\Module\Contracts\ModuleRegistryInterface;
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

use App\Infrastructure\SSH\Services\SSHConnection;


Route::get('/test', function (
    SSHConnectionInterface $ssh,
    ModuleRegistryInterface $registry,
    DashboardService $dashboard
) {
    $ssh->connect(
        host: '109.122.247.89',
        port: 22,
        username: 'root',
        authenticationType: 'password',
        credential: '000000',
    );

    dd(
        $dashboard->overview()
    );
});



require __DIR__.'/settings.php';
