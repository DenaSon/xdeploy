<?php

declare(strict_types=1);

namespace Tests\Feature\Application\Server;

use App\Application\Server\Actions\ActivateServerAction;
use App\Application\Server\Actions\CreateServerAction;
use App\Application\Server\Actions\DeleteServerAction;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActiveServerLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $hostSequence = 10;

    public function test_first_server_is_active_and_later_servers_are_inactive(): void
    {
        $user = $this->createUser('09173431111');
        $otherUser = $this->createUser('09173431112');

        $action = app(CreateServerAction::class);

        $firstServer = $action->handle(
            $user,
            $this->serverAttributes('First server'),
        );

        $secondServer = $action->handle(
            $user,
            $this->serverAttributes('Second server'),
        );

        $otherUserServer = $action->handle(
            $otherUser,
            $this->serverAttributes('Other user server'),
        );

        $this->assertServerStatus(
            $firstServer,
            ServerStatus::Active,
        );

        $this->assertServerStatus(
            $secondServer,
            ServerStatus::Inactive,
        );

        $this->assertServerStatus(
            $otherUserServer,
            ServerStatus::Active,
        );

        $this->assertSame(
            1,
            $user->servers()->active()->count(),
        );

        $this->assertSame(
            1,
            $otherUser->servers()->active()->count(),
        );
    }

    public function test_selecting_a_server_activates_it_and_deactivates_only_the_same_users_servers(): void
    {
        $user = $this->createUser('09173431113');
        $otherUser = $this->createUser('09173431114');

        $previousServer = $this->createServer(
            $user,
            ServerStatus::Active,
        );

        $selectedServer = $this->createServer(
            $user,
            ServerStatus::Inactive,
        );

        $legacyDuplicate = $this->createServer(
            $user,
            ServerStatus::Active,
        );

        $otherUserServer = $this->createServer(
            $otherUser,
            ServerStatus::Active,
        );

        $activatedServer = app(ActivateServerAction::class)->handle(
            $user,
            $selectedServer,
        );

        $this->assertServerStatus(
            $activatedServer,
            ServerStatus::Active,
        );

        $this->assertServerStatus(
            $previousServer,
            ServerStatus::Inactive,
        );

        $this->assertServerStatus(
            $legacyDuplicate,
            ServerStatus::Inactive,
        );

        $this->assertServerStatus(
            $otherUserServer,
            ServerStatus::Active,
        );

        $this->assertSame(
            1,
            $user->servers()->active()->count(),
        );

        $this->assertSame(
            1,
            $otherUser->servers()->active()->count(),
        );
    }

    public function test_user_cannot_activate_another_users_server(): void
    {
        $user = $this->createUser('09173431115');
        $otherUser = $this->createUser('09173431116');

        $activeServer = $this->createServer(
            $user,
            ServerStatus::Active,
        );

        $foreignServer = $this->createServer(
            $otherUser,
            ServerStatus::Inactive,
        );

        $activationWasRejected = false;

        try {
            app(ActivateServerAction::class)->handle(
                $user,
                $foreignServer,
            );
        } catch (ModelNotFoundException) {
            $activationWasRejected = true;
        }

        $this->assertTrue(
            $activationWasRejected,
            'Activating another user\'s server should fail.',
        );

        $this->assertServerStatus(
            $activeServer,
            ServerStatus::Active,
        );

        $this->assertServerStatus(
            $foreignServer,
            ServerStatus::Inactive,
        );

        $this->assertSame(
            1,
            $user->servers()->active()->count(),
        );
    }

    public function test_deleting_the_active_server_promotes_the_latest_remaining_server(): void
    {
        $user = $this->createUser('09173431117');

        $activeServer = $this->createServer(
            $user,
            ServerStatus::Active,
        );

        $olderInactiveServer = $this->createServer(
            $user,
            ServerStatus::Inactive,
        );

        $latestInactiveServer = $this->createServer(
            $user,
            ServerStatus::Inactive,
        );

        app(DeleteServerAction::class)->handle(
            $user,
            (int) $activeServer->getKey(),
        );

        $this->assertModelMissing($activeServer);

        $this->assertServerStatus(
            $olderInactiveServer,
            ServerStatus::Inactive,
        );

        $this->assertServerStatus(
            $latestInactiveServer,
            ServerStatus::Active,
        );

        $this->assertSame(
            1,
            $user->servers()->active()->count(),
        );
    }

    public function test_deleting_an_inactive_server_keeps_the_current_server_active(): void
    {
        $user = $this->createUser('09173431118');

        $activeServer = $this->createServer(
            $user,
            ServerStatus::Active,
        );

        $inactiveServer = $this->createServer(
            $user,
            ServerStatus::Inactive,
        );

        app(DeleteServerAction::class)->handle(
            $user,
            (int) $inactiveServer->getKey(),
        );

        $this->assertModelMissing($inactiveServer);

        $this->assertServerStatus(
            $activeServer,
            ServerStatus::Active,
        );

        $this->assertSame(
            1,
            $user->servers()->active()->count(),
        );

        $this->assertSame(
            1,
            $user->servers()->count(),
        );
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        ServerStatus $status,
    ): Server {
        $server = new Server(
            $this->serverAttributes('Test server'),
        );

        $server->status = $status;

        $user->servers()->save($server);

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
     *     name: string,
     *     host: string,
     *     port: int,
     *     username: string
     * }
     */
    private function serverAttributes(string $name): array
    {
        $host = sprintf(
            '192.0.2.%d',
            $this->hostSequence++,
        );

        return [
            'name' => $name,
            'host' => $host,
            'port' => 22,
            'username' => 'root',
        ];
    }
}
