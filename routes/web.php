<?php

Route::livewire('/', 'public.landing')
    ->name('home');

require __DIR__.'/coreRoute.php';

require __DIR__.'/panelRoute.php';

require __DIR__.'/payments.php';
