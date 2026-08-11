<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Server;

use App\Application\Server\Actions\DeleteServerAction;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Exceptions\CloudServerDeletionNotAllowedException;
use App\Infrastructure\Security\Encryption\CredentialKeyRing;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DeleteServerActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCredentialEncryption();
    }

    public function test_user_provided_server_is_soft_deleted(): void
    {
        $user = $this->createUser(
            phone: '09121111111',
        );

        $server = $this->createServer(
            user: $user,
        );

        app(
            DeleteServerAction::class,
        )->handle(
            user: $user,
            serverId: $server->getKey(),
        );

        $this->assertSoftDeleted(
            'servers',
            [
                'id' => $server->getKey(),
            ],
        );

        self::assertNull(
            Server::query()->find(
                $server->getKey(),
            ),
        );

        self::assertNotNull(
            Server::withTrashed()->find(
                $server->getKey(),
            ),
        );
    }

    public function test_cloud_server_cannot_be_deleted(): void
    {
        $user = $this->createUser(
            phone: '09122222222',
        );

        $server = $this->createServer(
            user: $user,
            cloudProvider: 'arvan',
            cloudServerId: 'cloud-server-123',
        );

        $this->expectException(
            CloudServerDeletionNotAllowedException::class,
        );

        try {
            app(
                DeleteServerAction::class,
            )->handle(
                user: $user,
                serverId: $server->getKey(),
            );
        } finally {
            $this->assertDatabaseHas(
                'servers',
                [
                    'id' => $server->getKey(),
                    'deleted_at' => null,
                ],
            );
        }
    }

    public function test_user_cannot_delete_another_users_server(): void
    {
        $owner = $this->createUser(
            phone: '09123333333',
        );

        $otherUser = $this->createUser(
            phone: '09124444444',
        );

        $server = $this->createServer(
            user: $owner,
        );

        $this->expectException(
            ModelNotFoundException::class,
        );

        app(
            DeleteServerAction::class,
        )->handle(
            user: $otherUser,
            serverId: $server->getKey(),
        );
    }

    private function createUser(
        string $phone,
    ): User {
        return User::query()->create([
            'name' => 'Delete Server Test User',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        ?string $cloudProvider = null,
        ?string $cloudServerId = null,
    ): Server {
        return $user
            ->servers()
            ->create([
                'name' => 'Delete Server Test',
                'host' => '192.0.2.10',
                'port' => 22,
                'username' => 'root',
                'authentication_type' => AuthenticationType::Password,
                'credential' => 'test-password',
                'status' => ServerStatus::Active,
                'cloud_provider' => $cloudProvider,
                'cloud_server_id' => $cloudServerId,
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
