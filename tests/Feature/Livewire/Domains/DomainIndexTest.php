<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Domains;

use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Domains\Index as DomainsIndex;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

final class DomainIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_domains_workspace_renders_without_opening_ssh_during_initial_request(): void
    {
        $user = $this->createUser('09173432201');
        $server = $this->createServer($user);

        $this->app->bind(
            ConnectServerAction::class,
            static fn (): never => throw new LogicException(
                'Domains initial render must not connect through SSH.',
            ),
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.domains.index',
                    ['server' => $server],
                ),
            )
            ->assertOk()
            ->assertSee('دامنه‌ها و HTTPS')
            ->assertSee('دامنه‌ها');
    }

    public function test_domains_workspace_renders_an_active_domain_card(): void
    {
        $user = $this->createUser('09173432202');
        $server = $this->createServer($user);

        Livewire::actingAs($user)
            ->test(
                DomainsIndex::class,
                ['server' => $server],
            )
            ->call(
                'updateManagement',
                [
                    'application' => [
                        'state' => 'running',
                        'is_installed' => true,
                        'is_running' => true,
                    ],
                    'https' => [
                        'state' => 'enabled',
                        'domain' => 'panel.example.com',
                    ],
                ],
            )
            ->assertSet('loaded', true)
            ->assertSet('showDrawer', false)
            ->assertSee('panel.example.com')
            ->assertSee('HTTPS فعال');
    }

    public function test_domains_workspace_opens_the_setup_drawer_for_an_installed_application_without_a_domain(): void
    {
        $user = $this->createUser('09173432203');
        $server = $this->createServer($user);

        Livewire::actingAs($user)
            ->test(
                DomainsIndex::class,
                ['server' => $server],
            )
            ->call(
                'updateManagement',
                [
                    'application' => [
                        'state' => 'running',
                        'is_installed' => true,
                        'is_running' => true,
                    ],
                    'https' => [
                        'state' => 'disabled',
                        'domain' => null,
                    ],
                ],
            )
            ->call('openDomainDrawer')
            ->assertSet('showDrawer', true)
            ->assertSee('افزودن دامنه');
    }

    public function test_domains_workspace_rejects_a_foreign_server(): void
    {
        $user = $this->createUser('09173432204');
        $otherUser = $this->createUser('09173432205');
        $foreignServer = $this->createServer($otherUser);

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.domains.index',
                    ['server' => $foreignServer],
                ),
            )
            ->assertNotFound();
    }

    public function test_domains_workspace_requires_authentication(): void
    {
        $user = $this->createUser('09173432206');
        $server = $this->createServer($user);

        $this
            ->get(
                route(
                    'panel.servers.domains.index',
                    ['server' => $server],
                ),
            )
            ->assertRedirect(
                route('login'),
            );
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'phone' => $phone,
        ]);
    }

    private function createServer(User $user): Server
    {
        $server = new Server([
            'name' => 'domains-test-server-'.$user->getKey(),
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
