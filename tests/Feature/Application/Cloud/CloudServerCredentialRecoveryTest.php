<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Cloud;

use App\Application\Cloud\Services\CloudServerCredentialRecovery;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\SSH\Contracts\SSHCredentialVerifierInterface;
use App\Infrastructure\SSH\Exceptions\SSHPasswordRotationException;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CloudServerCredentialRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_pending_credential_is_promoted_after_remote_rotation_committed(): void
    {
        $server = $this->server(
            activeCredential: 'old-secret',
            pendingCredential: 'new-secret',
        );
        $calls = $this->calls();

        $recovery = new CloudServerCredentialRecovery(
            verifier: $this->verifier(
                acceptedPasswords: ['new-secret'],
                calls: $calls,
            ),
        );

        $recovery->recoverPendingCredentialIfNeeded(
            server: $server,
            markBootstrapCredentialRotated: true,
        );

        $fresh = $server->fresh();

        self::assertSame(['new-secret'], $calls->items);
        self::assertSame('new-secret', $fresh->credential);
        self::assertNull($fresh->pending_credential);
        self::assertFalse($fresh->hasPendingCredential());
        self::assertNotNull($fresh->bootstrap_credential_rotated_at);
    }

    public function test_stale_pending_credential_is_cleared_when_active_credential_still_works(): void
    {
        $server = $this->server(
            activeCredential: 'old-secret',
            pendingCredential: 'candidate-that-never-committed',
        );
        $calls = $this->calls();

        $recovery = new CloudServerCredentialRecovery(
            verifier: $this->verifier(
                acceptedPasswords: ['old-secret'],
                calls: $calls,
            ),
        );

        $recovery->recoverPendingCredentialIfNeeded(
            server: $server,
            markBootstrapCredentialRotated: true,
        );

        $fresh = $server->fresh();

        self::assertSame([
            'candidate-that-never-committed',
            'old-secret',
        ], $calls->items);
        self::assertSame('old-secret', $fresh->credential);
        self::assertNull($fresh->pending_credential);
        self::assertNull($fresh->bootstrap_credential_rotated_at);
    }

    public function test_ambiguous_rotation_preserves_both_credentials_and_remains_retryable(): void
    {
        $server = $this->server(
            activeCredential: 'old-secret',
            pendingCredential: 'new-secret',
        );
        $calls = $this->calls();

        $recovery = new CloudServerCredentialRecovery(
            verifier: $this->verifier(
                acceptedPasswords: [],
                calls: $calls,
            ),
        );

        try {
            $recovery->recoverPendingCredentialIfNeeded(
                server: $server,
                markBootstrapCredentialRotated: true,
            );

            self::fail(
                'Ambiguous credential state must remain retryable.',
            );
        } catch (CloudServerSshUnavailableException $exception) {
            self::assertStringContainsString(
                'ambiguous',
                $exception->getMessage(),
            );
        }

        $fresh = $server->fresh();

        self::assertSame([
            'new-secret',
            'old-secret',
        ], $calls->items);
        self::assertSame('old-secret', $fresh->credential);
        self::assertSame('new-secret', $fresh->pending_credential);
        self::assertTrue($fresh->hasPendingCredential());
        self::assertNull($fresh->bootstrap_credential_rotated_at);
    }

    private function server(
        string $activeCredential,
        string $pendingCredential,
    ): Server {
        $user = User::factory()->create();

        return Server::query()->create([
            'user_id' => $user->getKey(),
            'name' => 'credential-recovery-state-machine',
            'host' => '203.0.113.42',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => $activeCredential,
            'pending_credential' => $pendingCredential,
            'status' => ServerStatus::Inactive,
            'cloud_provider' => 'liara',
            'cloud_server_id' => 'credential-state-machine-vm',
            'cloud_region' => 'iran',
            'provisioned_at' => now(),
        ]);
    }

    private function calls(): object
    {
        return new class
        {
            /** @var list<string> */
            public array $items = [];
        };
    }

    /**
     * @param  list<string>  $acceptedPasswords
     */
    private function verifier(
        array $acceptedPasswords,
        object $calls,
    ): SSHCredentialVerifierInterface {
        return new class($acceptedPasswords, $calls) implements SSHCredentialVerifierInterface
        {
            /**
             * @param  list<string>  $acceptedPasswords
             */
            public function __construct(
                private array $acceptedPasswords,
                private object $calls,
            ) {}

            public function verifyCredential(
                Server $server,
                string $password,
            ): void {
                $this->calls->items[] = $password;

                if (! in_array(
                    $password,
                    $this->acceptedPasswords,
                    true,
                )) {
                    throw new SSHPasswordRotationException(
                        'Injected credential verification failure.',
                    );
                }
            }
        };
    }
}
