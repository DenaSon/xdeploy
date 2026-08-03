<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Security\Encryption\CredentialKeyRing;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Infrastructure\Security\Exceptions\CredentialEncryptionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class CredentialSecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            CredentialKeyRing::class,
            function (): CredentialKeyRing {
                $currentKeyId = config(
                    'security.server_credentials.current_key_id',
                );

                $keys = config(
                    'security.server_credentials.keys',
                );

                if (! is_string($currentKeyId)) {
                    throw new CredentialEncryptionException(
                        'Server credential current key ID must be a string.',
                    );
                }

                if (! is_array($keys)) {
                    throw new CredentialEncryptionException(
                        'Server credential keys must be an array.',
                    );
                }

                return new CredentialKeyRing(
                    currentKeyId: $currentKeyId,
                    encodedKeys: $keys,
                );
            },
        );

        $this->app->singleton(
            ServerCredentialCipher::class,
            function (Application $app): ServerCredentialCipher {
                return new ServerCredentialCipher(
                    keyRing: $app->make(
                        CredentialKeyRing::class,
                    ),
                );
            },
        );
    }
}
