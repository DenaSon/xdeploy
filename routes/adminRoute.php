<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Servers\ConfirmSupportPasskeyController;
use App\Http\Controllers\Admin\Servers\RevealSupportCredentialController;
use App\Http\Controllers\Admin\Servers\SupportPasskeyOptionsController;
use App\Http\Controllers\Admin\Users\StartUserImpersonationController;
use App\Http\Controllers\Support\ShowSupportMessageAttachmentController;
use App\Livewire\Admin\CloudProviders\Index as AdminCloudProvidersIndex;
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
use App\Livewire\Admin\Security\ConfirmPasskey as AdminConfirmPasskey;
use App\Livewire\Admin\Servers\Index as AdminServersIndex;
use App\Livewire\Admin\Servers\Show as AdminServersShow;
use App\Livewire\Admin\Settings\Index as AdminSettingsIndex;
use App\Livewire\Admin\Support\Index as AdminSupportIndex;
use App\Livewire\Admin\Support\Show as AdminSupportShow;
use App\Livewire\Admin\Users\Index as AdminUsersIndex;
use App\Livewire\Admin\Users\Show as AdminUsersShow;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Http\Controllers\PasskeyConfirmationController;

Route::middleware([
    'web',
    'auth',
    'admin',
])
    ->prefix('admin')
    ->as('admin.')
    ->group(function (): void {
        Route::livewire(
            '/passkey/confirm',
            AdminConfirmPasskey::class,
        )->name('passkey.confirm');

        Route::get(
            '/passkey/options',
            [PasskeyConfirmationController::class, 'index'],
        )
            ->middleware('throttle:6,1')
            ->name('passkey.options');

        Route::post(
            '/passkey/verify',
            [PasskeyConfirmationController::class, 'store'],
        )
            ->middleware('throttle:6,1')
            ->name('passkey.verify');

        Route::middleware('admin.passkey')
            ->group(function (): void {
                Route::livewire('/', AdminDashboard::class)
                    ->name('dashboard');

                Route::livewire(
                    '/cloud-providers',
                    AdminCloudProvidersIndex::class,
                )->name('cloud-providers.index');

                Route::livewire('/users', AdminUsersIndex::class)
                    ->name('users.index');
                Route::livewire('/users/{user}', AdminUsersShow::class)
                    ->name('users.show');
                Route::post(
                    '/users/{user}/impersonate',
                    StartUserImpersonationController::class,
                )->name('users.impersonate');

                Route::livewire('/servers', AdminServersIndex::class)
                    ->name('servers.index');
                Route::get(
                    '/servers/{adminServer}/support/passkey/options',
                    SupportPasskeyOptionsController::class,
                )
                    ->middleware('throttle:6,1')
                    ->name('servers.support.passkey.options');
                Route::post(
                    '/servers/{adminServer}/support/passkey/verify',
                    ConfirmSupportPasskeyController::class,
                )
                    ->middleware('throttle:6,1')
                    ->name('servers.support.passkey.verify');
                Route::post(
                    '/servers/{adminServer}/support/reveal-credential',
                    RevealSupportCredentialController::class,
                )
                    ->middleware('throttle:10,1')
                    ->name('servers.support.reveal-credential');
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

                Route::livewire('/support', AdminSupportIndex::class)
                    ->name('support.index');
                Route::get(
                    '/support/attachments/{attachment}',
                    [
                        ShowSupportMessageAttachmentController::class,
                        'admin',
                    ],
                )->whereNumber('attachment')
                    ->name('support.attachments.show');
                Route::livewire('/support/{supportRequestId}', AdminSupportShow::class)
                    ->whereNumber('supportRequestId')
                    ->name('support.show');

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

                Route::livewire('/settings', AdminSettingsIndex::class)
                    ->name('settings.index');
            });
    });
