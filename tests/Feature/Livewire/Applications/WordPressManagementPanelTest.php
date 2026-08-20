<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Applications;

use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Applications\WordPress\ManagementPanel;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class WordPressManagementPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_render_the_private_wordpress_runtime_panel(): void
    {
        $user = $this->createUser('09120000081');
        $server = $this->createServer($user, '192.0.2.81');

        Livewire::actingAs($user)
            ->test(
                ManagementPanel::class,
                ['serverId' => $server->getKey()],
            )
            ->assertSet('serverId', $server->getKey())
            ->assertSee('آمادگی WordPress')
            ->assertSee('127.0.0.1:8080')
            ->assertSee('دسترسی عمومی غیرفعال');
    }

    public function test_management_panel_rejects_a_foreign_server(): void
    {
        $owner = $this->createUser('09120000082');
        $otherUser = $this->createUser('09120000083');
        $server = $this->createServer($owner, '192.0.2.82');

        $this->expectException(
            ModelNotFoundException::class,
        );

        Livewire::actingAs($otherUser)
            ->test(
                ManagementPanel::class,
                ['serverId' => $server->getKey()],
            );
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'name' => 'WordPress Panel Test',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        string $host,
    ): Server {
        $server = new Server([
            'name' => 'WordPress Panel Server',
            'host' => $host,
            'port' => 22,
            'username' => 'root',
        ]);

        $server->status = ServerStatus::Active;
        $user->servers()->save($server);

        return $server->refresh();
    }
}
