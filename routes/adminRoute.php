<?php

declare(strict_types=1);

use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Documentation\Articles\Create as AdminDocumentationArticlesCreate;
use App\Livewire\Admin\Documentation\Articles\Edit as AdminDocumentationArticlesEdit;
use App\Livewire\Admin\Documentation\Articles\Index as AdminDocumentationArticlesIndex;
use App\Livewire\Admin\Documentation\Categories\Create as AdminDocumentationCategoriesCreate;
use App\Livewire\Admin\Documentation\Categories\Edit as AdminDocumentationCategoriesEdit;
use App\Livewire\Admin\Documentation\Categories\Index as AdminDocumentationCategoriesIndex;
use App\Livewire\Admin\Orders\Index as AdminOrdersIndex;
use App\Livewire\Admin\Orders\Show as AdminOrdersShow;
use App\Livewire\Admin\Pages\Create as AdminPagesCreate;
use App\Livewire\Admin\Pages\Edit as AdminPagesEdit;
use App\Livewire\Admin\Pages\Index as AdminPagesIndex;
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
        Route::livewire('/servers/{adminServer}', AdminServersShow::class)
            ->name('servers.show');

        Route::livewire('/orders', AdminOrdersIndex::class)
            ->name('orders.index');
        Route::livewire('/orders/{order}', AdminOrdersShow::class)
            ->name('orders.show');

        Route::livewire('/payments', AdminPaymentsIndex::class)
            ->name('payments.index');
        Route::livewire('/payments/{payment}', AdminPaymentsShow::class)
            ->name('payments.show');

        Route::livewire('/documentation', AdminDocumentationArticlesIndex::class)
            ->name('documentation.articles.index');
        Route::livewire('/documentation/articles/create', AdminDocumentationArticlesCreate::class)
            ->name('documentation.articles.create');
        Route::livewire('/documentation/articles/{article}/edit', AdminDocumentationArticlesEdit::class)
            ->name('documentation.articles.edit');
        Route::livewire('/documentation/categories', AdminDocumentationCategoriesIndex::class)
            ->name('documentation.categories.index');
        Route::livewire('/documentation/categories/create', AdminDocumentationCategoriesCreate::class)
            ->name('documentation.categories.create');
        Route::livewire('/documentation/categories/{category}/edit', AdminDocumentationCategoriesEdit::class)
            ->name('documentation.categories.edit');

        Route::livewire('/pages', AdminPagesIndex::class)
            ->name('pages.index');
        Route::livewire('/pages/create', AdminPagesCreate::class)
            ->name('pages.create');
        Route::livewire('/pages/{page}/edit', AdminPagesEdit::class)
            ->name('pages.edit');
    });
