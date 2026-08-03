<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Encryption;

use App\Infrastructure\Security\Exceptions\CredentialEncryptionException;

final class CredentialKeyRing
{
    private const KEY_BYTES = 32;

    /**
     * Decoded binary encryption keys.
     *
     * @var array<string, string>
     */
    private array $keys = [];

    /**
     * @param  array<string, string|null>  $encodedKeys
     */
    public function __construct(
        private readonly string $currentKeyId,
        array $encodedKeys,
    ) {
        if ($this->currentKeyId === '') {
            throw new CredentialEncryptionException(
                'Current server credential key ID is not configured.',
            );
        }

        foreach ($encodedKeys as $keyId => $encodedKey) {
            if (! is_string($keyId) || $keyId === '') {
                throw new CredentialEncryptionException(
                    'A server credential encryption key has an invalid ID.',
                );
            }

            if (! is_string($encodedKey) || $encodedKey === '') {
                throw new CredentialEncryptionException(
                    "Server credential encryption key [{$keyId}] is not configured.",
                );
            }

            $this->keys[$keyId] = $this->decodeKey(
                keyId: $keyId,
                encodedKey: $encodedKey,
            );
        }

        if (! array_key_exists($this->currentKeyId, $this->keys)) {
            throw new CredentialEncryptionException(
                "Current server credential encryption key [{$this->currentKeyId}] does not exist.",
            );
        }
    }

    public function currentKeyId(): string
    {
        return $this->currentKeyId;
    }

    public function currentKey(): string
    {
        return $this->key(
            $this->currentKeyId,
        );
    }

    public function key(string $keyId): string
    {
        $key = $this->keys[$keyId] ?? null;

        if ($key === null) {
            throw new CredentialEncryptionException(
                "Server credential encryption key [{$keyId}] is unavailable.",
            );
        }

        return $key;
    }

    private function decodeKey(
        string $keyId,
        string $encodedKey,
    ): string {
        if (! str_starts_with($encodedKey, 'base64:')) {
            throw new CredentialEncryptionException(
                "Server credential encryption key [{$keyId}] must use base64 encoding.",
            );
        }

        $decodedKey = base64_decode(
            string: substr($encodedKey, 7),
            strict: true,
        );

        if (
            $decodedKey === false
            || strlen($decodedKey) !== self::KEY_BYTES
        ) {
            throw new CredentialEncryptionException(
                "Server credential encryption key [{$keyId}] must contain exactly 32 bytes.",
            );
        }

        return $decodedKey;
    }
}
