<?php

declare(strict_types=1);

use App\Http\Controllers\Profile\GoogleEmailVerificationController;
use App\Http\Controllers\Servers\RevealServerCredentialController;
use App\Livewire\Applications\Index as ApplicationsIndex;
use App\Livewire\Applications\Show as ApplicationShow;
use App\Livewire\Domains\Index as DomainsIndex;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Orders\Show as OrderShow;
use App\Livewire\Profile\Edit as ProfileEdit;
use App\Livewire\Security\Index as SecurityIndex;
use App\Livewire\Servers\Buy as ServerBuy;
use App\Livewire\Servers\Console as ServerConsole;
use App\Livewire\Servers\Create as ServerCreate;
use App\Livewire\Servers\Dashboard as ServerDashboard;
use App\Livewire\Servers\Details as ServerDetails;
use App\Livewire\Servers\Edit as ServerEdit;
use App\Livewire\Servers\Index as ServersIndex;
use App\Livewire\Servers\Renew as ServerRenew;
use App\Livewire\Support\Create as SupportCreate;
use App\Livewire\Support\Index as SupportIndex;
use App\Livewire\Support\Show as SupportShow;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Http\Controllers\PasskeyLoginController;
use Laravel\Passkeys\Http\Controllers\PasskeyRegistrationController;

Route::middleware(['web', 'guest'])
    ->group(function (): void {
        Route::livewire(
            '/login',
            'auth.login-page',
        )->name('login');

        Route::livewire(
            '/verify',
            'auth.verify-otp-page',
        )->name('verify');

        Route::get(
            '/passkeys/login/options',
            [PasskeyLoginController::class, 'index'],
        )
            ->middleware('throttle:6,1')
            ->name('passkey.login-options');

        Route::post(
            '/passkeys/login',
            [PasskeyLoginController::class, 'store'],
        )
            ->middleware('throttle:6,1')
            ->name('passkey.login');
    });

Route::middleware(['web', 'auth'])
    ->prefix('panel')
    ->as('panel.')
    ->group(function (): void {
        Route::livewire(
            '/notifications',
            NotificationsIndex::class,
        )->name('notifications.index');

        Route::livewire(
            '/support',
            SupportIndex::class,
        )->name('support.index');

        Route::livewire(
            '/support/create',
            SupportCreate::class,
        )->name('support.create');

        Route::livewire(
            '/support/{supportRequestId}',
            SupportShow::class,
        )->whereNumber('supportRequestId')
            ->name('support.show');

        Route::livewire(
            '/profile',
            ProfileEdit::class,
        )->name('profile');

        Route::get(
            '/profile/email/google',
            [GoogleEmailVerificationController::class, 'redirect'],
        )
            ->middleware('throttle:10,1')
            ->name('profile.email.google.redirect');

        Route::get(
            '/profile/email/google/callback',
            [GoogleEmailVerificationController::class, 'callback'],
        )
            ->middleware('throttle:20,1')
            ->name('profile.email.google.callback');

        Route::livewire(
            '/security',
            SecurityIndex::class,
        )->name('security');

        Route::get(
            '/security/passkeys/options',
            [PasskeyRegistrationController::class, 'index'],
        )
            ->middleware('throttle:6,1')
            ->name('security.passkeys.options');

        Route::post(
            '/security/passkeys',
            [PasskeyRegistrationController::class, 'store'],
        )
            ->middleware('throttle:6,1')
            ->name('security.passkeys.store');

        Route::livewire(
            '/servers',
            ServersIndex::class,
        )->name('servers.index');

        /*
         * Static server routes must be declared before /servers/{server}.
         */
        Route::livewire(
            '/servers/buy',
            ServerBuy::class,
        )->name('servers.buy');

        Route::livewire(
            '/servers/create',
            ServerCreate::class,
        )->name('servers.create');

        Route::livewire(
            '/servers/{server}/edit',
            ServerEdit::class,
        )->name('servers.edit');

        Route::livewire(
            '/servers/{server}/details',
            ServerDetails::class,
        )->name('servers.details');

        Route::post(
            '/servers/{server}/credential/reveal',
            RevealServerCredentialController::class,
        )
            ->middleware('throttle:10,1')
            ->name('servers.credential.reveal');

        Route::livewire(
            '/servers/{server}/renew',
            ServerRenew::class,
        )->name('servers.renew');

        Route::livewire(
            '/servers/{server}/console',
            ServerConsole::class,
        )->name('servers.console');

        Route::livewire(
            '/servers/{server}',
            ServerDashboard::class,
        )->name('servers.dashboard');

        Route::livewire(
            '/servers/{server}/applications',
            ApplicationsIndex::class,
        )->name('servers.applications.index');

        Route::livewire(
            '/servers/{server}/domains',
            DomainsIndex::class,
        )->name('servers.domains.index');

        Route::livewire(
            '/servers/{server}/applications/{application}',
            ApplicationShow::class,
        )->name('servers.applications.show');

        Route::livewire(
            '/orders/{order}',
            OrderShow::class,
        )->name('orders.show');
    });

require __DIR__.'/amneziawg.php';
