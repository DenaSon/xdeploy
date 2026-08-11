<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Application\Server\Actions\UpdateServerAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\Security\Encryption\CredentialKeyRing;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Livewire\Servers\Edit;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class ServerEditSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCredentialEncryption();
    }

    public function test_user_cannot_open_another_users_server_edit_page(): void
    {
        $owner = $this->createUser(
            phone: '09121111111',
        );

        $attacker = $this->createUser(
            phone: '09122222222',
        );

        $server = $this->createServer(
            user: $owner,
            credential: 'owner-secret-password',
        );

        $response = $this
            ->actingAs($attacker)
            ->get(
                route(
                    'panel.servers.edit',
                    $server,
                ),
            );

        $response->assertNotFound();
    }

    public function test_existing_server_credential_is_not_exposed_to_livewire_state(): void
    {
        $user = $this->createUser(
            phone: '09123333333',
        );

        $server = $this->createServer(
            user: $user,
            credential: 'super-secret-password',
        );

        $this->actingAs($user);

        Livewire::test(
            Edit::class,
            [
                'server' => $server,
            ],
        )
            ->assertSet(
                'credential',
                '',
            );
    }

    public function test_update_action_cannot_update_another_users_server(): void
    {
        $owner = $this->createUser(
            phone: '09124444444',
        );

        $attacker = $this->createUser(
            phone: '09125555555',
        );

        $server = $this->createServer(
            user: $owner,
            credential: 'owner-password',
        );

        $action = app(
            UpdateServerAction::class,
        );

        $this->expectException(
            ModelNotFoundException::class,
        );

        $action->handle(
            user: $attacker,
            server: $server,
            attributes: [
                'name' => 'Hacked server',
                'host' => $server->host,
                'port' => $server->port,
                'username' => $server->username,
                'credential' => '',
            ],
        );
    }

    public function test_update_action_cannot_change_server_host(): void
    {
        $user = $this->createUser(
            phone: '09127777777',
        );

        $server = $this->createServer(
            user: $user,
            credential: 'original-password',
        );

        $originalHost = $server->host;

        $updatedServer = app(
            UpdateServerAction::class,
        )->handle(
            user: $user,
            server: $server,
            attributes: [
                'host' => '198.51.100.50',
                'port' => 2222,
                'username' => 'deploy',
                'credential' => '',
            ],
        );

        self::assertSame(
            $originalHost,
            $updatedServer->host,
        );

        self::assertSame(
            2222,
            $updatedServer->port,
        );

        self::assertSame(
            'deploy',
            $updatedServer->username,
        );
    }

    public function test_empty_credential_keeps_existing_server_credential(): void
    {
        $user = $this->createUser(
            phone: '09126666666',
        );

        $server = $this->createServer(
            user: $user,
            credential: 'original-password',
        );

        $originalEncryptedCredential =
            $server->getRawOriginal(
                'credential',
            );

        $updatedServer = app(
            UpdateServerAction::class,
        )->handle(
            user: $user,
            server: $server,
            attributes: [
                'name' => 'Updated server name',
                'host' => '198.51.100.50',
                'port' => $server->port,
                'username' => $server->username,
                'credential' => '',
            ],
        );

        self::assertSame(
            'Updated server name',
            $updatedServer->name,
        );

        self::assertSame(
            '192.0.2.10',
            $updatedServer->host,
        );

        self::assertSame(
            'original-password',
            $updatedServer->credential,
        );

        self::assertSame(
            $originalEncryptedCredential,
            $updatedServer->getRawOriginal(
                'credential',
            ),
        );
    }

    private function createUser(
        string $phone,
    ): User {
        return User::query()->create([
            'name' => 'Security Test User',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        string $credential,
    ): Server {
        return $user
            ->servers()
            ->create([
                'name' => 'Security Test Server',
                'host' => '192.0.2.10',
                'port' => 22,
                'username' => 'root',
                'authentication_type' => AuthenticationType::Password,
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
