<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Casts;

use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Infrastructure\Security\Exceptions\CredentialEncryptionException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stringable;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
final class ServerCredentialCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw new CredentialEncryptionException(
                'The stored server credential must be a string.',
            );
        }

        $cipher = $this->cipher();

        /*
         * New xDeploy envelope.
         */
        if ($cipher->isEncryptedValue($value)) {
            return $cipher->decrypt(
                encryptedValue: $value,
                context: $this->existingContext(
                    $attributes,
                ),
            );
        }

        /*
         * Legacy Laravel encrypted cast.
         *
         * This fallback should remain until all legacy credentials
         * have been migrated.
         */
        return Crypt::decryptString(
            $value,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null>
     */
    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): array {
        if ($value === null) {
            return [
                $key => null,
            ];
        }

        if (
            ! is_string($value)
            && ! $value instanceof Stringable
        ) {
            throw new InvalidArgumentException(
                'The server credential must be a string.',
            );
        }

        $plaintext = (string) $value;

        if ($plaintext === '') {
            throw new InvalidArgumentException(
                'The server credential cannot be empty.',
            );
        }

        $context = $this->contextForWriting(
            $attributes,
        );

        return [
            $key => $this->cipher()->encrypt(
                plaintext: $plaintext,
                context: $context,
            ),

            'credential_context' => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function existingContext(
        array $attributes,
    ): string {
        $context = $attributes['credential_context']
            ?? null;

        if (! is_string($context) || $context === '') {
            throw new CredentialEncryptionException(
                'The encrypted server credential has no context.',
            );
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function contextForWriting(
        array $attributes,
    ): string {
        $existingContext = $attributes['credential_context']
            ?? null;

        if (
            is_string($existingContext)
            && $existingContext !== ''
        ) {
            return $existingContext;
        }

        return (string) Str::uuid();
    }

    private function cipher(): ServerCredentialCipher
    {
        return app(
            ServerCredentialCipher::class,
        );
    }
}
