<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ServerPendingCredentialEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_credential_is_encrypted_with_an_independent_context_and_can_be_cleared(): void
    {
        $user = User::factory()->create();

        $server = Server::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'credential-recovery-test',
            'host' => '203.0.113.40',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => 'active-secret',
            'pending_credential' => 'pending-secret',
            'status' => ServerStatus::Inactive,
            'cloud_provider' => 'liara',
            'cloud_server_id' => 'credential-recovery-vm',
            'cloud_region' => 'iran',
            'provisioned_at' => now(),
        ]);

        $raw = DB::table('servers')
            ->where('id', $server->getKey())
            ->firstOrFail();

        self::assertIsString($raw->credential);
        self::assertIsString($raw->pending_credential);
        self::assertNotSame('active-secret', $raw->credential);
        self::assertNotSame('pending-secret', $raw->pending_credential);
        self::assertStringStartsWith(
            ServerCredentialCipher::PREFIX,
            $raw->credential,
        );
        self::assertStringStartsWith(
            ServerCredentialCipher::PREFIX,
            $raw->pending_credential,
        );
        self::assertIsString($raw->credential_context);
        self::assertIsString($raw->pending_credential_context);
        self::assertNotSame(
            $raw->credential_context,
            $raw->pending_credential_context,
        );

        $fresh = $server->fresh();

        self::assertSame('active-secret', $fresh->credential);
        self::assertSame('pending-secret', $fresh->pending_credential);
        self::assertTrue($fresh->hasCredential());
        self::assertTrue($fresh->hasPendingCredential());

        $serialized = $fresh->toArray();

        self::assertArrayNotHasKey('credential', $serialized);
        self::assertArrayNotHasKey('credential_context', $serialized);
        self::assertArrayNotHasKey('pending_credential', $serialized);
        self::assertArrayNotHasKey('pending_credential_context', $serialized);

        $fresh->forceFill([
            'pending_credential' => null,
        ])->saveOrFail();

        $cleared = DB::table('servers')
            ->where('id', $server->getKey())
            ->firstOrFail();

        self::assertNull($cleared->pending_credential);
        self::assertNull($cleared->pending_credential_context);
        self::assertSame('active-secret', $server->fresh()->credential);
    }
}
