<?php

Route::middleware(['web', 'guest'])->group(function () {

    Route::livewire('/login', 'auth.login-page')
        ->name('login');

    Route::livewire('/verify', 'auth.verify-otp-page')
        ->name('verification');
});

Route::middleware(['web', 'auth'])->prefix('panel')->as('panel.')->group(function () {

    Route::livewire('/', 'panel.dashboard')
        ->name('dashboard');

    Route::livewire('/modules', 'modules.index')
        ->name('modules.index');

    Route::livewire('/modules/{module}', 'modules.show')
        ->name('modules.show');
});
