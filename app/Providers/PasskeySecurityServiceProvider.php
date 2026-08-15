<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Authentication\Listeners\RecordPasskeySecurityAudit;
use App\Http\Responses\Security\PasskeyConfirmationResponse;
use App\Http\Responses\Security\PasskeyLoginResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Contracts\PasskeyConfirmationResponse as PasskeyConfirmationResponseContract;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;

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

    public function boot(): void
    {
        Event::listen(
            PasskeyRegistered::class,
            fn (PasskeyRegistered $event) => app(
                RecordPasskeySecurityAudit::class,
            )->registered($event),
        );

        Event::listen(
            PasskeyVerified::class,
            fn (PasskeyVerified $event) => app(
                RecordPasskeySecurityAudit::class,
            )->verified($event),
        );

        Event::listen(
            PasskeyDeleted::class,
            fn (PasskeyDeleted $event) => app(
                RecordPasskeySecurityAudit::class,
            )->deleted($event),
        );
    }
}
