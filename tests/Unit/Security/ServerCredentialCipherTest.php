<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Infrastructure\Security\Encryption\CredentialKeyRing;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Infrastructure\Security\Exceptions\CredentialEncryptionException;
use PHPUnit\Framework\TestCase;

final class ServerCredentialCipherTest extends TestCase
{
    public function test_it_encrypts_and_decrypts_a_server_credential(): void
    {
        $cipher = $this->makeCipher();

        $encrypted = $cipher->encrypt(
            plaintext: 'my-secret-password',
            context: 'server-context-1',
        );

        self::assertStringStartsWith(
            ServerCredentialCipher::PREFIX,
            $encrypted,
        );

        self::assertSame(
            'my-secret-password',
            $cipher->decrypt(
                encryptedValue: $encrypted,
                context: 'server-context-1',
            ),
        );
    }

    public function test_it_produces_different_ciphertexts_for_equal_credentials(): void
    {
        $cipher = $this->makeCipher();

        $first = $cipher->encrypt(
            plaintext: 'same-password',
            context: 'server-context-1',
        );

        $second = $cipher->encrypt(
            plaintext: 'same-password',
            context: 'server-context-1',
        );

        self::assertNotSame(
            $first,
            $second,
        );

        self::assertSame(
            'same-password',
            $cipher->decrypt(
                encryptedValue: $first,
                context: 'server-context-1',
            ),
        );

        self::assertSame(
            'same-password',
            $cipher->decrypt(
                encryptedValue: $second,
                context: 'server-context-1',
            ),
        );
    }

    public function test_it_rejects_a_credential_with_another_context(): void
    {
        $cipher = $this->makeCipher();

        $encrypted = $cipher->encrypt(
            plaintext: 'my-secret-password',
            context: 'server-context-1',
        );

        $this->expectException(
            CredentialEncryptionException::class,
        );

        $cipher->decrypt(
            encryptedValue: $encrypted,
            context: 'server-context-2',
        );
    }

    public function test_it_rewraps_the_data_key_using_a_new_master_key(): void
    {
        $oldKey = $this->credentialKey();
        $newKey = $this->credentialKey();

        $oldCipher = new ServerCredentialCipher(
            keyRing: new CredentialKeyRing(
                currentKeyId: 'v1',
                encodedKeys: [
                    'v1' => $oldKey,
                ],
            ),
        );

        $encrypted = $oldCipher->encrypt(
            plaintext: 'my-secret-password',
            context: 'server-context-1',
        );

        $newCipher = new ServerCredentialCipher(
            keyRing: new CredentialKeyRing(
                currentKeyId: 'v2',
                encodedKeys: [
                    'v1' => $oldKey,
                    'v2' => $newKey,
                ],
            ),
        );

        self::assertTrue(
            $newCipher->needsRewrap(
                $encrypted,
            ),
        );

        $rewrapped = $newCipher->rewrap(
            encryptedValue: $encrypted,
            context: 'server-context-1',
        );

        self::assertNotSame(
            $encrypted,
            $rewrapped,
        );

        self::assertFalse(
            $newCipher->needsRewrap(
                $rewrapped,
            ),
        );

        self::assertSame(
            'my-secret-password',
            $newCipher->decrypt(
                encryptedValue: $rewrapped,
                context: 'server-context-1',
            ),
        );
    }

    public function test_it_cannot_decrypt_a_rewrapped_credential_without_the_new_key(): void
    {
        $oldKey = $this->credentialKey();
        $newKey = $this->credentialKey();

        $oldCipher = new ServerCredentialCipher(
            keyRing: new CredentialKeyRing(
                currentKeyId: 'v1',
                encodedKeys: [
                    'v1' => $oldKey,
                ],
            ),
        );

        $encrypted = $oldCipher->encrypt(
            plaintext: 'my-secret-password',
            context: 'server-context-1',
        );

        $rotationCipher = new ServerCredentialCipher(
            keyRing: new CredentialKeyRing(
                currentKeyId: 'v2',
                encodedKeys: [
                    'v1' => $oldKey,
                    'v2' => $newKey,
                ],
            ),
        );

        $rewrapped = $rotationCipher->rewrap(
            encryptedValue: $encrypted,
            context: 'server-context-1',
        );

        $cipherWithoutNewKey = new ServerCredentialCipher(
            keyRing: new CredentialKeyRing(
                currentKeyId: 'v1',
                encodedKeys: [
                    'v1' => $oldKey,
                ],
            ),
        );

        $this->expectException(
            CredentialEncryptionException::class,
        );

        $cipherWithoutNewKey->decrypt(
            encryptedValue: $rewrapped,
            context: 'server-context-1',
        );
    }

    public function test_it_rejects_tampered_ciphertext(): void
    {
        $cipher = $this->makeCipher();

        $encrypted = $cipher->encrypt(
            plaintext: 'my-secret-password',
            context: 'server-context-1',
        );

        $lastCharacter = substr(
            $encrypted,
            -1,
        );

        $replacement = $lastCharacter === 'A'
            ? 'B'
            : 'A';

        $tampered = substr(
            $encrypted,
            0,
            -1,
        ).$replacement;

        $this->expectException(
            CredentialEncryptionException::class,
        );

        $cipher->decrypt(
            encryptedValue: $tampered,
            context: 'server-context-1',
        );
    }

    private function makeCipher(): ServerCredentialCipher
    {
        return new ServerCredentialCipher(
            keyRing: new CredentialKeyRing(
                currentKeyId: 'v1',
                encodedKeys: [
                    'v1' => $this->credentialKey(),
                ],
            ),
        );
    }

    private function credentialKey(): string
    {
        return 'base64:'.base64_encode(
            random_bytes(32),
        );
    }
}
