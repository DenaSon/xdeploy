<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {

    Route::livewire('/login', 'auth.login-page')
        ->name('login');

});
Route::livewire('/verify/{phone}', 'auth.verify-otp-page')
    ->name('verify');

Route::middleware(['web', 'auth'])->prefix('panel')->as('panel.')->group(function () {

    Route::livewire('/', 'panel.dashboard')
        ->name('dashboard');

    Route::livewire('/modules', 'modules.index')
        ->name('modules.index');

});
