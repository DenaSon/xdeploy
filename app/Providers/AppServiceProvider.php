<?php

namespace App\Providers;

use App;
use App\Models\Server;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Passkeys::ignoreRoutes();
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        App::setLocale('fa');

        Route::bind(
            'server',
            static function (string $value): Server {
                $user = Auth::user();

                if (! $user instanceof User) {
                    throw new AuthenticationException;
                }

                return $user->servers()
                    ->whereKey($value)
                    ->firstOrFail();
            },
        );
    }
}
