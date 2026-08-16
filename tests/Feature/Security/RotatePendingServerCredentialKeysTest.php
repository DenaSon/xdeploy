<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\Security\Encryption\CredentialKeyRing;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RotatePendingServerCredentialKeysTest extends TestCase
{
    use RefreshDatabase;

    public function test_rotation_rewraps_active_and_pending_credentials_before_old_key_retirement(): void
    {
        $this->configureKeys(
            current: 'old',
            keys: [
                'old' => $this->encodedKey('a'),
                'new' => $this->encodedKey('b'),
            ],
        );

        $user = User::factory()->create();

        $server = Server::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'key-rotation-recovery-test',
            'host' => '203.0.113.41',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'active-before-rotation',
            'pending_credential' => 'pending-before-rotation',
            'status' => ServerStatus::Inactive,
            'cloud_provider' => 'liara',
            'cloud_server_id' => 'key-rotation-vm',
            'cloud_region' => 'iran',
            'provisioned_at' => now(),
        ]);

        $before = DB::table('servers')
            ->where('id', $server->getKey())
            ->firstOrFail();

        $oldCipher = app(ServerCredentialCipher::class);

        self::assertFalse($oldCipher->needsRewrap($before->credential));
        self::assertFalse($oldCipher->needsRewrap($before->pending_credential));

        $this->configureKeys(
            current: 'new',
            keys: [
                'old' => $this->encodedKey('a'),
                'new' => $this->encodedKey('b'),
            ],
        );

        $newCipher = app(ServerCredentialCipher::class);

        self::assertTrue($newCipher->needsRewrap($before->credential));
        self::assertTrue($newCipher->needsRewrap($before->pending_credential));

        $this->artisan('security:rotate-server-credential-keys')
            ->assertSuccessful();

        $after = DB::table('servers')
            ->where('id', $server->getKey())
            ->firstOrFail();

        self::assertFalse($newCipher->needsRewrap($after->credential));
        self::assertFalse($newCipher->needsRewrap($after->pending_credential));
        self::assertNotSame($before->credential, $after->credential);
        self::assertNotSame(
            $before->pending_credential,
            $after->pending_credential,
        );

        /*
         * Retire the old wrapping key entirely. Both active and pending
         * credentials must remain decryptable with the new master key only.
         */
        $this->configureKeys(
            current: 'new',
            keys: [
                'new' => $this->encodedKey('b'),
            ],
        );

        $fresh = Server::query()->findOrFail($server->getKey());

        self::assertSame('active-before-rotation', $fresh->credential);
        self::assertSame('pending-before-rotation', $fresh->pending_credential);
    }

    /** @param array<string, string> $keys */
    private function configureKeys(string $current, array $keys): void
    {
        config()->set('security.server_credentials.current_key_id', $current);
        config()->set('security.server_credentials.keys', $keys);

        $this->app->forgetInstance(CredentialKeyRing::class);
        $this->app->forgetInstance(ServerCredentialCipher::class);
    }

    private function encodedKey(string $byte): string
    {
        return 'base64:'.base64_encode(
            str_repeat($byte, 32),
        );
    }
}
