<?php

use App\Application\Module\Actions\InstallModuleAction;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Services\ModuleService;
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
    InstallModuleAction $action,
    ModuleService $moduleService,

) {

    $ssh->connect(
        host: '127.0.0.1',
        port: 2222,
        username: 'root',
        authenticationType: 'password',
        credential: 'xdeploy',
    );

    $action->execute(
        ModuleType::Marzban,
    );

    $info = $moduleService->inspect(
        ModuleType::Marzban,
    );

    return [
        'status' => 'installed',
        'state' => $info->state->value,
    ];
});

require __DIR__.'/settings.php';
