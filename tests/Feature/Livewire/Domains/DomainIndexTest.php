<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Domains;

use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Domains\Index as DomainsIndex;
use App\Models\PublicEndpoint;
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

    public function test_domains_workspace_adopts_an_existing_active_marzban_domain(): void
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
                $this->management(
                    httpsState: 'enabled',
                    domain: 'panel.example.com',
                ),
            )
            ->assertSet('loaded', true)
            ->assertSet('showDrawer', false)
            ->assertSee('panel.example.com')
            ->assertSee('HTTPS فعال');

        $this->assertDatabaseHas(
            'public_endpoints',
            [
                'server_id' => $server->getKey(),
                'application_type' => ApplicationType::Marzban->value,
                'domain' => 'panel.example.com',
            ],
        );

        self::assertNotNull(
            PublicEndpoint::query()->firstOrFail()->activated_at,
        );
    }

    public function test_domains_workspace_selects_an_available_application_before_domain_setup(): void
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
                $this->management(
                    httpsState: 'disabled',
                ),
            )
            ->call('openDomainDrawer')
            ->assertSet('showDrawer', true)
            ->assertSet(
                'selectedApplication',
                ApplicationType::Marzban->value,
            )
            ->assertSet('showSetup', false)
            ->assertSee('برنامه مقصد را انتخاب کنید')
            ->assertSee('Marzban')
            ->call('continueDomainSetup')
            ->assertSet('showSetup', true)
            ->assertSee('برنامه مقصد');
    }

    public function test_pending_endpoint_survives_page_state_and_can_be_cancelled(): void
    {
        $user = $this->createUser('09173432204');
        $server = $this->createServer($user);

        $endpoint = PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'pending.example.com',
        ]);

        Livewire::actingAs($user)
            ->test(
                DomainsIndex::class,
                ['server' => $server],
            )
            ->call(
                'updateManagement',
                $this->management(
                    httpsState: 'disabled',
                ),
            )
            ->assertSee('pending.example.com')
            ->assertSee('در انتظار راه‌اندازی')
            ->call(
                'manageEndpoint',
                (int) $endpoint->getKey(),
            )
            ->assertSet('showDrawer', true)
            ->assertSet('showSetup', true)
            ->call(
                'cancelPendingEndpoint',
                (int) $endpoint->getKey(),
            )
            ->assertSet('showDrawer', false);

        $this->assertDatabaseMissing(
            'public_endpoints',
            [
                'id' => $endpoint->getKey(),
            ],
        );
    }

    public function test_active_endpoint_cannot_be_cancelled_from_pending_flow(): void
    {
        $user = $this->createUser('09173432205');
        $server = $this->createServer($user);

        $endpoint = PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'active.example.com',
            'activated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(
                DomainsIndex::class,
                ['server' => $server],
            )
            ->call(
                'cancelPendingEndpoint',
                (int) $endpoint->getKey(),
            );

        $this->assertDatabaseHas(
            'public_endpoints',
            [
                'id' => $endpoint->getKey(),
                'domain' => 'active.example.com',
            ],
        );
    }

    public function test_domains_workspace_rejects_a_foreign_server(): void
    {
        $user = $this->createUser('09173432206');
        $otherUser = $this->createUser('09173432207');
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
        $user = $this->createUser('09173432208');
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

    /**
     * @return array<string, mixed>
     */
    private function management(
        string $httpsState,
        ?string $domain = null,
    ): array {
        return [
            'application' => [
                'state' => 'running',
                'is_installed' => true,
                'is_running' => true,
            ],
            'https' => [
                'state' => $httpsState,
                'domain' => $domain,
            ],
        ];
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
