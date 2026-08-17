<?php

use App\Livewire\Documentation\Index as DocumentationIndex;
use App\Livewire\Documentation\Show as DocumentationShow;
use App\Livewire\Pages\Show as PublicPageShow;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'public.landing')
    ->name('home');

Route::livewire('/docs', DocumentationIndex::class)
    ->name('docs.index');
Route::livewire('/docs/{categorySlug}/{articleSlug}', DocumentationShow::class)
    ->name('docs.show');

Route::livewire('/pages/{slug}', PublicPageShow::class)
    ->name('pages.show');

require __DIR__.'/panelRoute.php';

require __DIR__.'/integrations.php';

require __DIR__.'/payments.php';

require __DIR__.'/adminRoute.php';
