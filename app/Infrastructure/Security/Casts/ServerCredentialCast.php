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
                    key: $key,
                    attributes: $attributes,
                ),
            );
        }

        /*
         * Legacy Laravel encrypted cast. Only the active credential can have
         * legacy ciphertext; pending credentials were introduced after the
         * xDeploy envelope became canonical.
         */
        if ($key !== 'credential') {
            throw new CredentialEncryptionException(
                'The pending server credential is not stored in the expected encrypted envelope.',
            );
        }

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
        $contextAttribute = $this->contextAttribute($key);

        if ($value === null) {
            return [
                $key => null,
                $contextAttribute => null,
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
            key: $key,
            attributes: $attributes,
        );

        return [
            $key => $this->cipher()->encrypt(
                plaintext: $plaintext,
                context: $context,
            ),
            $contextAttribute => $context,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function existingContext(
        string $key,
        array $attributes,
    ): string {
        $context = $attributes[$this->contextAttribute($key)]
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
        string $key,
        array $attributes,
    ): string {
        $existingContext = $attributes[$this->contextAttribute($key)]
            ?? null;

        if (
            is_string($existingContext)
            && $existingContext !== ''
        ) {
            return $existingContext;
        }

        return (string) Str::uuid();
    }

    private function contextAttribute(string $key): string
    {
        return match ($key) {
            'credential' => 'credential_context',
            'pending_credential' => 'pending_credential_context',
            default => throw new InvalidArgumentException(
                sprintf(
                    'Unsupported server credential attribute [%s].',
                    $key,
                ),
            ),
        };
    }

    private function cipher(): ServerCredentialCipher
    {
        return app(
            ServerCredentialCipher::class,
        );
    }
}
