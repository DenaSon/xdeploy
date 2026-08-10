<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Server;

use App\Application\Server\Actions\CreateServerAction;
use App\Application\Server\Actions\DeleteServerAction;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ServerStatusLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $hostSequence = 10;

    private int $nameSequence = 1;

    public function test_server_is_inactive_by_default_when_status_is_not_explicitly_provided(): void
    {
        $user = $this->createUser(
            '09173431111',
        );

        $server = app(CreateServerAction::class)->handle(
            user: $user,
            attributes: $this->serverAttributes(),
        );

        $this->assertServerStatus(
            server: $server,
            expectedStatus: ServerStatus::Inactive,
        );
    }

    public function test_server_can_be_created_as_active_explicitly(): void
    {
        $user = $this->createUser(
            '09173431112',
        );

        $server = app(CreateServerAction::class)->handle(
            user: $user,
            attributes: $this->serverAttributes(),
            status: ServerStatus::Active,
        );

        $this->assertServerStatus(
            server: $server,
            expectedStatus: ServerStatus::Active,
        );
    }

    public function test_multiple_servers_can_be_active_at_the_same_time(): void
    {
        $user = $this->createUser(
            '09173431113',
        );

        $action = app(
            CreateServerAction::class,
        );

        $firstServer = $action->handle(
            user: $user,
            attributes: $this->serverAttributes(),
            status: ServerStatus::Active,
        );

        $secondServer = $action->handle(
            user: $user,
            attributes: $this->serverAttributes(),
            status: ServerStatus::Active,
        );

        $this->assertServerStatus(
            server: $firstServer,
            expectedStatus: ServerStatus::Active,
        );

        $this->assertServerStatus(
            server: $secondServer,
            expectedStatus: ServerStatus::Active,
        );

        $this->assertSame(
            2,
            $user
                ->servers()
                ->active()
                ->count(),
        );
    }

    public function test_deleting_an_active_server_does_not_change_remaining_server_statuses(): void
    {
        $user = $this->createUser(
            '09173431114',
        );

        $serverToDelete = $this->createServer(
            user: $user,
            status: ServerStatus::Active,
        );

        $remainingActiveServer = $this->createServer(
            user: $user,
            status: ServerStatus::Active,
        );

        $remainingInactiveServer = $this->createServer(
            user: $user,
            status: ServerStatus::Inactive,
        );

        app(DeleteServerAction::class)->handle(
            user: $user,
            serverId: (int) $serverToDelete->getKey(),
        );

        $this->assertModelMissing(
            $serverToDelete,
        );

        $this->assertServerStatus(
            server: $remainingActiveServer,
            expectedStatus: ServerStatus::Active,
        );

        $this->assertServerStatus(
            server: $remainingInactiveServer,
            expectedStatus: ServerStatus::Inactive,
        );
    }

    public function test_deleting_an_inactive_server_does_not_change_remaining_server_statuses(): void
    {
        $user = $this->createUser(
            '09173431115',
        );

        $serverToDelete = $this->createServer(
            user: $user,
            status: ServerStatus::Inactive,
        );

        $activeServer = $this->createServer(
            user: $user,
            status: ServerStatus::Active,
        );

        $inactiveServer = $this->createServer(
            user: $user,
            status: ServerStatus::Inactive,
        );

        app(DeleteServerAction::class)->handle(
            user: $user,
            serverId: (int) $serverToDelete->getKey(),
        );

        $this->assertModelMissing(
            $serverToDelete,
        );

        $this->assertServerStatus(
            server: $activeServer,
            expectedStatus: ServerStatus::Active,
        );

        $this->assertServerStatus(
            server: $inactiveServer,
            expectedStatus: ServerStatus::Inactive,
        );
    }

    public function test_user_cannot_delete_another_users_server(): void
    {
        $user = $this->createUser(
            '09173431116',
        );

        $otherUser = $this->createUser(
            '09173431117',
        );

        $ownServer = $this->createServer(
            user: $user,
            status: ServerStatus::Active,
        );

        $foreignServer = $this->createServer(
            user: $otherUser,
            status: ServerStatus::Active,
        );

        try {
            app(DeleteServerAction::class)->handle(
                user: $user,
                serverId: (int) $foreignServer->getKey(),
            );

            $this->fail(
                'Deleting another user\'s server should fail.',
            );
        } catch (ModelNotFoundException) {
            // Expected.
        }

        $this->assertModelExists(
            $ownServer,
        );

        $this->assertModelExists(
            $foreignServer,
        );

        $this->assertServerStatus(
            server: $ownServer,
            expectedStatus: ServerStatus::Active,
        );

        $this->assertServerStatus(
            server: $foreignServer,
            expectedStatus: ServerStatus::Active,
        );
    }

    private function createUser(
        string $phone,
    ): User {
        return User::query()->create([
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        ServerStatus $status,
    ): Server {
        $server = new Server([
            ...$this->serverAttributes(),

            'name' => sprintf(
                'test-server-%d',
                $this->nameSequence++,
            ),
        ]);

        $server->status = $status;

        $user
            ->servers()
            ->save($server);

        return $server->refresh();
    }

    private function assertServerStatus(
        Server $server,
        ServerStatus $expectedStatus,
    ): void {
        $this->assertSame(
            $expectedStatus,
            $server->refresh()->status,
        );
    }

    /**
     * @return array{
     *     host: string,
     *     port: int,
     *     username: string
     * }
     */
    private function serverAttributes(): array
    {
        return [
            'host' => sprintf(
                '192.0.2.%d',
                $this->hostSequence++,
            ),

            'port' => 22,

            'username' => 'root',
        ];
    }
}
