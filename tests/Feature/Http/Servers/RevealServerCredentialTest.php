<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Servers;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\Security\Encryption\CredentialKeyRing;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RevealServerCredentialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCredentialEncryption();
    }

    public function test_guest_cannot_reveal_server_credential(): void
    {
        $server = $this->createServer(
            user: $this->createUser('09121110001'),
            credential: 'guest-secret',
        );

        $this
            ->postJson(
                route(
                    'panel.servers.credential.reveal',
                    $server,
                ),
            )
            ->assertRedirect(
                route('login'),
            );
    }

    public function test_user_cannot_reveal_another_users_server_credential(): void
    {
        $owner = $this->createUser('09121110002');
        $attacker = $this->createUser('09121110003');

        $server = $this->createServer(
            user: $owner,
            credential: 'owner-secret',
        );

        $this
            ->actingAs($attacker)
            ->postJson(
                route(
                    'panel.servers.credential.reveal',
                    $server,
                ),
            )
            ->assertNotFound();
    }

    public function test_owner_can_reveal_password_credential_with_no_store_headers(): void
    {
        $user = $this->createUser('09121110004');

        $server = $this->createServer(
            user: $user,
            credential: 'current-server-password',
        );

        $response = $this
            ->actingAs($user)
            ->postJson(
                route(
                    'panel.servers.credential.reveal',
                    $server,
                ),
            );

        $response
            ->assertOk()
            ->assertExactJson([
                'credential' => 'current-server-password',
            ])
            ->assertHeader(
                'Pragma',
                'no-cache',
            )
            ->assertHeader(
                'Referrer-Policy',
                'no-referrer',
            );

        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get(
                'Cache-Control',
            ),
        );
    }

    public function test_ssh_key_credential_cannot_be_revealed(): void
    {
        $user = $this->createUser('09121110005');

        $server = $this->createServer(
            user: $user,
            credential: 'private-key-material',
            authenticationType: AuthenticationType::SSHKey,
        );

        $this
            ->actingAs($user)
            ->postJson(
                route(
                    'panel.servers.credential.reveal',
                    $server,
                ),
            )
            ->assertNotFound()
            ->assertDontSee('private-key-material');
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'name' => 'Credential Reveal User',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        string $credential,
        AuthenticationType $authenticationType = AuthenticationType::Password,
    ): Server {
        return $user
            ->servers()
            ->create([
                'name' => 'Credential Reveal Server',
                'host' => '192.0.2.25',
                'port' => 22,
                'username' => 'ubuntu',
                'authentication_type' => $authenticationType,
                'credential' => $credential,
                'status' => ServerStatus::Active,
            ]);
    }

    private function configureCredentialEncryption(): void
    {
        config()->set(
            'security.server_credentials.current_key_id',
            'test-v1',
        );

        config()->set(
            'security.server_credentials.keys',
            [
                'test-v1' => 'base64:'.base64_encode(
                    str_repeat(
                        'x',
                        32,
                    ),
                ),
            ],
        );

        app()->forgetInstance(
            CredentialKeyRing::class,
        );

        app()->forgetInstance(
            ServerCredentialCipher::class,
        );
    }
}
