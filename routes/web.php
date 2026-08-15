<?php

use App\Livewire\Pages\Show as PublicPageShow;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'public.landing')
    ->name('home');

Route::livewire('/pages/{slug}', PublicPageShow::class)
    ->name('pages.show');

require __DIR__.'/panelRoute.php';

require __DIR__.'/payments.php';

require __DIR__.'/adminRoute.php';
