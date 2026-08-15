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
use LogicException;

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
        $this->assertSafeProductionConfiguration();

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

    private function assertSafeProductionConfiguration(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        $userHandleSecret = (string) config(
            'passkeys.user_handle_secret',
        );
        $appKey = (string) config('app.key');
        $relyingPartyId = (string) config(
            'passkeys.relying_party_id',
        );
        $allowedOrigins = (array) config(
            'passkeys.allowed_origins',
            [],
        );

        if (
            $userHandleSecret === ''
            || $userHandleSecret === $appKey
        ) {
            throw new LogicException(
                'Production Passkeys require a stable PASSKEYS_USER_HANDLE_SECRET independent from APP_KEY.',
            );
        }

        if (
            $relyingPartyId === ''
            || $relyingPartyId === 'localhost'
        ) {
            throw new LogicException(
                'Production Passkeys require an explicit non-localhost relying party ID.',
            );
        }

        if ($allowedOrigins === []) {
            throw new LogicException(
                'Production Passkeys require at least one allowed HTTPS origin.',
            );
        }

        foreach ($allowedOrigins as $origin) {
            if (
                ! is_string($origin)
                || ! str_starts_with($origin, 'https://')
            ) {
                throw new LogicException(
                    'Production Passkey origins must use HTTPS.',
                );
            }
        }
    }
}
