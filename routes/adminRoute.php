<?php

declare(strict_types=1);

use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Orders\Show as AdminOrdersShow;
use App\Livewire\Admin\Payments\Index as AdminPaymentsIndex;
use App\Livewire\Admin\Payments\Show as AdminPaymentsShow;
use App\Livewire\Admin\Servers\Index as AdminServersIndex;
use App\Livewire\Admin\Servers\Show as AdminServersShow;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Admin\Users\Show as AdminUsersShow;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'admin',
])
    ->prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::livewire('/', AdminDashboard::class)
            ->name('dashboard');

        Route::livewire('/users', AdminUsersIndex::class)
            ->name('users.index');
        Route::livewire('/users/{user}', AdminUsersShow::class)
            ->name('users.show');

        Route::livewire('/servers', AdminServersIndex::class)
            ->name('servers.index');
        Route::livewire('/servers/{server}', AdminServersShow::class)
            ->name('servers.show');

        Route::livewire('/orders', AdminOrdersIndex::class)
            ->name('orders.index');
        Route::livewire('/orders/{order}', AdminOrdersShow::class)
            ->name('orders.show');

        Route::livewire('/payments', AdminPaymentsIndex::class)
            ->name('payments.index');
        Route::livewire('/payments/{payment}', AdminPaymentsShow::class)
            ->name('payments.show');
    });
