<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Applications;

use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\ApplicationCatalogItem;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class ApplicationShowRuntimeLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_initial_show_request_renders_local_state_without_resolving_ssh(): void
    {
        $user = $this->createUser(
            '09120000031',
        );

        $server = $this->createServer(
            $user,
        );

        $this->createCatalogItem();

        $this->app->bind(
            ConnectServerAction::class,
            static fn (): never => throw new LogicException(
                'Application show must not resolve SSH during the initial page request.',
            ),
        );

        $this
            ->actingAs($user)
            ->get(route(
                'panel.servers.applications.show',
                [
                    'server' => $server,
                    'application' => 'n8n',
                ],
            ))
            ->assertOk()
            ->assertSee('n8n')
            ->assertSee('Workflow automation')
            ->assertSee('در حال بررسی')
            ->assertSee('در حال دریافت وضعیت برنامه')
            ->assertSee('wire:init="loadRuntime"', false)
            ->assertDontSee('wire:click="install"', false);
    }

    public function test_initial_show_request_rejects_a_foreign_server(): void
    {
        $owner = $this->createUser(
            '09120000032',
        );

        $attacker = $this->createUser(
            '09120000033',
        );

        $server = $this->createServer(
            $owner,
        );

        $this->createCatalogItem();

        $this
            ->actingAs($attacker)
            ->get(route(
                'panel.servers.applications.show',
                [
                    'server' => $server,
                    'application' => 'n8n',
                ],
            ))
            ->assertNotFound();
    }

    private function createCatalogItem(): ApplicationCatalogItem
    {
        return ApplicationCatalogItem::query()->create([
            'slug' => 'n8n',
            'name' => 'n8n',
            'short_description' => 'Workflow automation',
            'description' => 'Automation platform',
            'icon' => 'lucide.workflow',
            'is_published' => true,
            'sort_order' => 10,
        ]);
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
    ): Server {
        $server = new Server([
            'name' => 'runtime-loading-test-server-'.$user->getKey(),
            'host' => '192.0.2.'.(30 + (int) $user->getKey()),
            'port' => 22,
            'username' => 'root',
        ]);

        $server->status = ServerStatus::Active;

        $user
            ->servers()
            ->save($server);

        return $server->refresh();
    }
}
