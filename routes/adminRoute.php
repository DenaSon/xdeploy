<?php

declare(strict_types=1);

use App\Livewire\Admin\Dashboard as AdminDashboard;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'admin',
])
    ->prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::livewire(
            '/',
            AdminDashboard::class,
        )->name('dashboard');
    });
