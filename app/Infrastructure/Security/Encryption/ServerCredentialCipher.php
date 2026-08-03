<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Encryption;

use App\Infrastructure\Security\Exceptions\CredentialEncryptionException;
use JsonException;
use SensitiveParameter;
use Throwable;

final readonly class ServerCredentialCipher
{
    public const PREFIX = 'xdeploy:credential:';

    private const VERSION = 1;

    private const VERSION_LABEL = 'v1';

    private const KEY_BYTES = 32;

    public function __construct(
        private CredentialKeyRing $keyRing,
    ) {
        if (
            ! extension_loaded('sodium')
            || ! function_exists(
                'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt',
            )
        ) {
            throw new CredentialEncryptionException(
                'The PHP Sodium extension is required for server credential encryption.',
            );
        }
    }

    public function encrypt(
        #[SensitiveParameter]
        string $plaintext,
        string $context,
    ): string {
        $this->ensureContextIsValid($context);

        $dataEncryptionKey =
            sodium_crypto_aead_xchacha20poly1305_ietf_keygen();

        try {
            $payloadNonce = random_bytes(
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES,
            );

            $encryptedPayload =
                sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    message: $plaintext,
                    additional_data: $this->payloadAdditionalData(
                        context: $context,
                    ),
                    nonce: $payloadNonce,
                    key: $dataEncryptionKey,
                );

            $keyId = $this->keyRing->currentKeyId();

            $wrapNonce = random_bytes(
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES,
            );

            $wrappedKey =
                sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    message: $dataEncryptionKey,
                    additional_data: $this->keyAdditionalData(
                        context: $context,
                        keyId: $keyId,
                    ),
                    nonce: $wrapNonce,
                    key: $this->keyRing->currentKey(),
                );

            return $this->encodeEnvelope([
                'v' => self::VERSION,
                'kid' => $keyId,
                'wn' => $this->encodeBinary($wrapNonce),
                'wk' => $this->encodeBinary($wrappedKey),
                'pn' => $this->encodeBinary($payloadNonce),
                'ct' => $this->encodeBinary($encryptedPayload),
            ]);
        } catch (CredentialEncryptionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CredentialEncryptionException(
                'The server credential could not be encrypted.',
                0,
                $exception,
            );
        } finally {
            sodium_memzero($dataEncryptionKey);
        }
    }

    public function decrypt(
        string $encryptedValue,
        string $context,
    ): string {
        $this->ensureContextIsValid($context);

        try {
            $envelope = $this->decodeEnvelope(
                $encryptedValue,
            );

            $dataEncryptionKey = $this->unwrapDataEncryptionKey(
                envelope: $envelope,
                context: $context,
            );

            try {
                $plaintext =
                    sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                        ciphertext: $this->decodeBinary(
                            encoded: $envelope['ct'],
                        ),
                        additional_data: $this->payloadAdditionalData(
                            context: $context,
                        ),
                        nonce: $this->decodeNonce(
                            encoded: $envelope['pn'],
                        ),
                        key: $dataEncryptionKey,
                    );

                if ($plaintext === false) {
                    throw new CredentialEncryptionException(
                        'The server credential could not be authenticated.',
                    );
                }

                return $plaintext;
            } finally {
                sodium_memzero($dataEncryptionKey);
            }
        } catch (CredentialEncryptionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CredentialEncryptionException(
                'The server credential could not be decrypted.',
                0,
                $exception,
            );
        }
    }

    public function isEncryptedValue(string $value): bool
    {
        return str_starts_with(
            haystack: $value,
            needle: self::PREFIX,
        );
    }

    public function needsRewrap(string $encryptedValue): bool
    {
        $envelope = $this->decodeEnvelope(
            $encryptedValue,
        );

        return $envelope['kid']
            !== $this->keyRing->currentKeyId();
    }

    /**
     * Rotate the master key without decrypting the actual credential payload.
     */
    public function rewrap(
        string $encryptedValue,
        string $context,
    ): string {
        $this->ensureContextIsValid($context);

        $envelope = $this->decodeEnvelope(
            $encryptedValue,
        );

        $currentKeyId = $this->keyRing->currentKeyId();

        if ($envelope['kid'] === $currentKeyId) {
            return $encryptedValue;
        }

        $dataEncryptionKey = $this->unwrapDataEncryptionKey(
            envelope: $envelope,
            context: $context,
        );

        try {
            $newWrapNonce = random_bytes(
                SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES,
            );

            $newWrappedKey =
                sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
                    message: $dataEncryptionKey,
                    additional_data: $this->keyAdditionalData(
                        context: $context,
                        keyId: $currentKeyId,
                    ),
                    nonce: $newWrapNonce,
                    key: $this->keyRing->currentKey(),
                );

            $envelope['kid'] = $currentKeyId;
            $envelope['wn'] = $this->encodeBinary(
                $newWrapNonce,
            );
            $envelope['wk'] = $this->encodeBinary(
                $newWrappedKey,
            );

            return $this->encodeEnvelope(
                $envelope,
            );
        } catch (Throwable $exception) {
            throw new CredentialEncryptionException(
                'The server credential key could not be rotated.',
                0,
                $exception,
            );
        } finally {
            sodium_memzero($dataEncryptionKey);
        }
    }

    /**
     * @param array{
     *     v: int,
     *     kid: string,
     *     wn: string,
     *     wk: string,
     *     pn: string,
     *     ct: string
     * } $envelope
     */
    private function unwrapDataEncryptionKey(
        array $envelope,
        string $context,
    ): string {
        $dataEncryptionKey =
            sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                ciphertext: $this->decodeBinary(
                    encoded: $envelope['wk'],
                ),
                additional_data: $this->keyAdditionalData(
                    context: $context,
                    keyId: $envelope['kid'],
                ),
                nonce: $this->decodeNonce(
                    encoded: $envelope['wn'],
                ),
                key: $this->keyRing->key(
                    $envelope['kid'],
                ),
            );

        if (
            $dataEncryptionKey === false
            || strlen($dataEncryptionKey) !== self::KEY_BYTES
        ) {
            throw new CredentialEncryptionException(
                'The server credential encryption key could not be authenticated.',
            );
        }

        return $dataEncryptionKey;
    }

    private function payloadAdditionalData(
        string $context,
    ): string {
        return implode('|', [
            'xdeploy',
            'server-credential',
            self::VERSION_LABEL,
            "context:{$context}",
            'payload',
        ]);
    }

    private function keyAdditionalData(
        string $context,
        string $keyId,
    ): string {
        return implode('|', [
            'xdeploy',
            'server-credential',
            self::VERSION_LABEL,
            "context:{$context}",
            "key:{$keyId}",
            'data-encryption-key',
        ]);
    }

    /**
     * @param array{
     *     v: int,
     *     kid: string,
     *     wn: string,
     *     wk: string,
     *     pn: string,
     *     ct: string
     * } $envelope
     */
    private function encodeEnvelope(array $envelope): string
    {
        try {
            $json = json_encode(
                value: $envelope,
                flags: JSON_THROW_ON_ERROR,
            );

            return self::PREFIX
                .self::VERSION_LABEL
                .':'
                .$this->encodeBinary($json);
        } catch (JsonException $exception) {
            throw new CredentialEncryptionException(
                'The server credential envelope could not be encoded.',
                0,
                $exception,
            );
        }
    }

    /**
     * @return array{
     *     v: int,
     *     kid: string,
     *     wn: string,
     *     wk: string,
     *     pn: string,
     *     ct: string
     * }
     */
    private function decodeEnvelope(
        string $encryptedValue,
    ): array {
        if (! $this->isEncryptedValue($encryptedValue)) {
            throw new CredentialEncryptionException(
                'The given value is not an xDeploy credential envelope.',
            );
        }

        $encodedEnvelope = substr(
            string: $encryptedValue,
            offset: strlen(
                self::PREFIX.self::VERSION_LABEL.':',
            ),
        );

        if (
            ! str_starts_with(
                haystack: $encryptedValue,
                needle: self::PREFIX.self::VERSION_LABEL.':',
            )
            || $encodedEnvelope === ''
        ) {
            throw new CredentialEncryptionException(
                'The server credential envelope version is unsupported.',
            );
        }

        try {
            $decoded = json_decode(
                json: $this->decodeBinary(
                    encoded: $encodedEnvelope,
                ),
                associative: true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new CredentialEncryptionException(
                'The server credential envelope is invalid.',
                0,
                $exception,
            );
        }

        if (! is_array($decoded)) {
            throw new CredentialEncryptionException(
                'The server credential envelope is invalid.',
            );
        }

        foreach (
            ['v', 'kid', 'wn', 'wk', 'pn', 'ct'] as $requiredField
        ) {
            if (! array_key_exists($requiredField, $decoded)) {
                throw new CredentialEncryptionException(
                    "The server credential envelope is missing [{$requiredField}].",
                );
            }
        }

        if ((int) $decoded['v'] !== self::VERSION) {
            throw new CredentialEncryptionException(
                "Unsupported server credential version [{$decoded['v']}].",
            );
        }

        foreach (
            ['kid', 'wn', 'wk', 'pn', 'ct'] as $stringField
        ) {
            if (
                ! is_string($decoded[$stringField])
                || $decoded[$stringField] === ''
            ) {
                throw new CredentialEncryptionException(
                    "The server credential envelope field [{$stringField}] is invalid.",
                );
            }
        }

        return [
            'v' => self::VERSION,
            'kid' => $decoded['kid'],
            'wn' => $decoded['wn'],
            'wk' => $decoded['wk'],
            'pn' => $decoded['pn'],
            'ct' => $decoded['ct'],
        ];
    }

    private function encodeBinary(string $binary): string
    {
        return sodium_bin2base64(
            string: $binary,
            id: SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
        );
    }

    private function decodeBinary(string $encoded): string
    {
        try {
            return sodium_base642bin(
                string: $encoded,
                id: SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING,
            );
        } catch (Throwable $exception) {
            throw new CredentialEncryptionException(
                'The server credential contains invalid binary data.',
                0,
                $exception,
            );
        }
    }

    private function decodeNonce(string $encoded): string
    {
        $nonce = $this->decodeBinary(
            $encoded,
        );

        if (
            strlen($nonce)
            !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES
        ) {
            throw new CredentialEncryptionException(
                'The server credential nonce has an invalid length.',
            );
        }

        return $nonce;
    }

    private function ensureContextIsValid(
        string $context,
    ): void {
        if ($context === '') {
            throw new CredentialEncryptionException(
                'The server credential context is missing.',
            );
        }
    }
}
