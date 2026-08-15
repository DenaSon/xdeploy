<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Responses\Security\PasskeyConfirmationResponse;
use App\Http\Responses\Security\PasskeyLoginResponse;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse as PasskeyConfirmationResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

final class PasskeySecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            PasskeyLoginResponseContract::class,
            PasskeyLoginResponse::class,
        );

        $this->app->singleton(
            PasskeyConfirmationResponseContract::class,
            PasskeyConfirmationResponse::class,
        );
    }
}
