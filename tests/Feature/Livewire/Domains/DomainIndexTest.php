<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Domains;

use App\Application\PublicEndpoint\Contracts\PublicEndpointDriverInterface;
use App\Application\PublicEndpoint\DTOs\PublicEndpointApplicationStatus;
use App\Application\PublicEndpoint\Operations\RunPublicEndpointOperationJob;
use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationOperationStatus;
use App\Domain\Application\Shared\Enums\ApplicationOperationType;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointDnsPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointServerPreflightResult;
use App\Domain\PublicEndpoint\Enums\PublicEndpointOperationStatus;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Livewire\Domains\Index as DomainsIndex;
use App\Models\ApplicationOperation;
use App\Models\PublicEndpoint;
use App\Models\PublicEndpointOperation;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

final class DomainIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_domains_workspace_renders_without_opening_ssh_during_initial_request(): void
    {
        $user = $this->createUser('09173432201');
        $server = $this->createServer($user);

        $this->app->bind(
            PublicEndpointDriverRegistry::class,
            static fn (): never => throw new \LogicException(
                'Initial render must not resolve public endpoint drivers.',
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
            ->assertSee('دامنه‌ها و HTTPS');
    }

    public function test_domains_workspace_lists_both_installed_endpoint_capable_applications(): void
    {
        $user = $this->createUser('09173432202');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();

        $this->bindDrivers($marzban, $n8n);

        Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->call('openDomainDrawer')
            ->assertSet('loaded', true)
            ->assertSet('unavailable', false)
            ->assertSee('Marzban')
            ->assertSee('n8n')
            ->call('selectApplication', ApplicationType::N8n->value)
            ->assertSet('selectedApplication', ApplicationType::N8n->value)
            ->call('continueDomainSetup')
            ->assertSet('showSetup', true)
            ->assertSee('برنامه مقصد');
    }

    public function test_server_wide_ssh_failure_stops_inspecting_remaining_applications(): void
    {
        $user = $this->createUser('09173432211');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();
        $marzban->statusException = new SSHConnectionException(
            'test connection closed',
        );

        $this->bindDrivers($marzban, $n8n);

        Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->assertSet('loaded', true)
            ->assertSet('unavailable', true)
            ->assertSet('statuses', [
                ApplicationType::Marzban->value => ['unavailable' => true],
                ApplicationType::N8n->value => ['unavailable' => true],
            ]);

        $this->assertSame(1, $marzban->statusCalls);
        $this->assertSame(0, $n8n->statusCalls);
    }

    public function test_driver_specific_failure_does_not_stop_inspecting_remaining_applications(): void
    {
        $user = $this->createUser('09173432212');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();
        $marzban->statusException = new \LogicException(
            'test driver failure',
        );

        $this->bindDrivers($marzban, $n8n);

        Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->assertSet('loaded', true)
            ->assertSet('unavailable', false)
            ->assertSet(
                'statuses.'.ApplicationType::Marzban->value,
                ['unavailable' => true],
            );

        $this->assertSame(1, $marzban->statusCalls);
        $this->assertSame(1, $n8n->statusCalls);
    }

    public function test_domains_workspace_adopts_existing_active_n8n_endpoint(): void
    {
        $user = $this->createUser('09173432203');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();
        $n8n->runtimeState = PublicEndpointRuntimeState::Enabled;
        $n8n->runtimeDomain = 'automation.example.com';

        $this->bindDrivers($marzban, $n8n);

        Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->assertSee('automation.example.com')
            ->assertSee('HTTPS فعال');

        $this->assertDatabaseHas('public_endpoints', [
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n->value,
            'domain' => 'automation.example.com',
        ]);

        $this->assertNotNull(
            PublicEndpoint::query()
                ->where('application_type', ApplicationType::N8n->value)
                ->firstOrFail()
                ->activated_at,
        );
    }

    public function test_existing_marzban_endpoint_keeps_n8n_available_for_new_domain(): void
    {
        $user = $this->createUser('09173432204');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();

        PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'domain' => 'panel.example.com',
            'activated_at' => now(),
        ]);

        $marzban->runtimeState = PublicEndpointRuntimeState::Enabled;
        $marzban->runtimeDomain = 'panel.example.com';

        $this->bindDrivers($marzban, $n8n);

        Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->call('openDomainDrawer')
            ->assertSet('selectedApplication', ApplicationType::N8n->value)
            ->assertSee('n8n');
    }

    public function test_active_n8n_endpoint_removal_is_queued_and_polled(): void
    {
        Queue::fake();

        $user = $this->createUser('09173432205');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();

        $endpoint = PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'domain' => 'automation.example.com',
            'activated_at' => now(),
        ]);

        $n8n->runtimeState = PublicEndpointRuntimeState::Enabled;
        $n8n->runtimeDomain = 'automation.example.com';

        $this->bindDrivers($marzban, $n8n);

        $component = Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->call('removeEndpoint', (int) $endpoint->getKey())
            ->assertSet('removalOperationActive', true)
            ->assertSet('endpointError', null);

        $this->assertSame(0, $n8n->disableCalls);
        $this->assertDatabaseHas('public_endpoints', [
            'id' => $endpoint->getKey(),
        ]);

        $operation = PublicEndpointOperation::query()->firstOrFail();

        $this->assertSame(
            PublicEndpointOperationStatus::Pending,
            $operation->status,
        );

        Queue::assertPushed(
            RunPublicEndpointOperationJob::class,
            static fn (RunPublicEndpointOperationJob $job): bool => $job->operationId === $operation->getKey(),
        );

        (new RunPublicEndpointOperationJob(
            (int) $operation->getKey(),
        ))->handle(
            app(PublicEndpointDriverRegistry::class),
            app(SSHConnectionInterface::class),
        );

        $operation->refresh();
        $endpoint->refresh();

        $this->assertSame(1, $n8n->disableCalls);
        $this->assertSame(
            PublicEndpointOperationStatus::Succeeded,
            $operation->status,
        );
        $this->assertNull($endpoint->activated_at);

        $component
            ->call('pollRemovalOperation')
            ->assertSet('removalOperationActive', false)
            ->assertSet('showDrawer', false)
            ->assertSet('endpointError', null);

        $this->assertDatabaseMissing('public_endpoints', [
            'id' => $endpoint->getKey(),
        ]);
    }

    public function test_active_application_operation_blocks_endpoint_removal(): void
    {
        Queue::fake();

        $user = $this->createUser('09173432210');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();

        $endpoint = PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'domain' => 'automation.example.com',
            'activated_at' => now(),
        ]);

        ApplicationOperation::query()->create([
            'user_id' => $user->getKey(),
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::Marzban,
            'operation' => ApplicationOperationType::Install,
            'status' => ApplicationOperationStatus::Running,
        ]);

        $n8n->runtimeState = PublicEndpointRuntimeState::Enabled;
        $n8n->runtimeDomain = 'automation.example.com';

        $this->bindDrivers($marzban, $n8n);

        Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->call('removeEndpoint', (int) $endpoint->getKey())
            ->assertSet('removalOperationActive', false)
            ->assertSet(
                'endpointError',
                'یک عملیات دیگر روی این سرور در حال انجام است. پس از پایان آن دوباره تلاش کنید.',
            );

        $this->assertSame(0, $n8n->disableCalls);
        $this->assertDatabaseCount('public_endpoint_operations', 0);
        Queue::assertNotPushed(RunPublicEndpointOperationJob::class);
    }

    public function test_pending_endpoint_can_be_cancelled_without_remote_mutation(): void
    {
        $user = $this->createUser('09173432206');
        $server = $this->createServer($user);
        [$marzban, $n8n] = $this->drivers();

        $endpoint = PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'domain' => 'pending.example.com',
        ]);

        $this->bindDrivers($marzban, $n8n);

        Livewire::actingAs($user)
            ->test(DomainsIndex::class, ['server' => $server])
            ->call('loadDomains')
            ->call('manageEndpoint', (int) $endpoint->getKey())
            ->assertSet('showSetup', true)
            ->call('cancelPendingEndpoint', (int) $endpoint->getKey())
            ->assertSet('showDrawer', false);

        $this->assertDatabaseMissing('public_endpoints', [
            'id' => $endpoint->getKey(),
        ]);
        $this->assertSame(0, $n8n->disableCalls);
    }

    public function test_domains_workspace_rejects_a_foreign_server(): void
    {
        $user = $this->createUser('09173432207');
        $otherUser = $this->createUser('09173432208');
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
        $user = $this->createUser('09173432209');
        $server = $this->createServer($user);

        $this
            ->get(
                route(
                    'panel.servers.domains.index',
                    ['server' => $server],
                ),
            )
            ->assertRedirect(route('login'));
    }

    /** @return array{0: DomainIndexFakeDriver, 1: DomainIndexFakeDriver} */
    private function drivers(): array
    {
        return [
            new DomainIndexFakeDriver(ApplicationType::Marzban, 'Marzban'),
            new DomainIndexFakeDriver(ApplicationType::N8n, 'n8n'),
        ];
    }

    private function bindDrivers(
        DomainIndexFakeDriver ...$drivers,
    ): void {
        $this->app->instance(
            PublicEndpointDriverRegistry::class,
            new PublicEndpointDriverRegistry($drivers),
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
        return $user->servers()->create([
            'name' => 'domains-test-server-'.$user->getKey(),
            'host' => '192.0.2.'.(30 + (int) $user->getKey()),
            'port' => 22,
            'username' => 'root',
        ]);
    }
}

final class DomainIndexFakeDriver implements PublicEndpointDriverInterface
{
    public PublicEndpointRuntimeState $runtimeState = PublicEndpointRuntimeState::Disabled;

    public ?string $runtimeDomain = null;

    public int $disableCalls = 0;

    public int $statusCalls = 0;

    public ?\Throwable $statusException = null;

    public function __construct(
        private readonly ApplicationType $applicationType,
        private readonly string $applicationName,
    ) {}

    public function type(): ApplicationType
    {
        return $this->applicationType;
    }

    public function name(): string
    {
        return $this->applicationName;
    }

    public function description(): string
    {
        return "Public endpoint for {$this->applicationName}.";
    }

    public function icon(): string
    {
        return $this->applicationType === ApplicationType::N8n
            ? 'lucide.workflow'
            : 'lucide.box';
    }

    public function openUrl(PublicEndpointDomain $domain): string
    {
        return "https://{$domain->value}/";
    }

    public function status(
        User $user,
        Server $server,
    ): PublicEndpointApplicationStatus {
        $this->statusCalls++;

        if ($this->statusException !== null) {
            throw $this->statusException;
        }

        return $this->statusResult(
            state: $this->runtimeState,
            domain: $this->runtimeDomain,
        );
    }

    public function preflight(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointPreflightResult {
        return new PublicEndpointPreflightResult(
            dns: new PublicEndpointDnsPreflightResult(
                domain: $domain->value,
                serverIpv4Address: '203.0.113.20',
                resolvedIpv4Addresses: ['203.0.113.20'],
                resolvedIpv6Addresses: [],
            ),
            server: new PublicEndpointServerPreflightResult(
                layoutState: 'supported',
                layoutSupported: true,
                managedCaddyDetected: true,
                hasPortConflict: false,
                ready: true,
            ),
        );
    }

    public function enable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
        $this->runtimeState = PublicEndpointRuntimeState::Enabled;
        $this->runtimeDomain = $domain->value;

        return $this->statusResult(
            state: $this->runtimeState,
            domain: $this->runtimeDomain,
        );
    }

    public function disable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
        $this->disableCalls++;
        $this->runtimeState = PublicEndpointRuntimeState::Disabled;
        $this->runtimeDomain = null;

        return $this->statusResult(
            PublicEndpointRuntimeState::Disabled,
        );
    }

    private function statusResult(
        PublicEndpointRuntimeState $state,
        ?string $domain = null,
    ): PublicEndpointApplicationStatus {
        return new PublicEndpointApplicationStatus(
            application: new ApplicationInfo(
                ApplicationState::Running,
            ),
            endpoint: new PublicEndpointRuntimeInfo(
                state: $state,
                domain: $domain,
            ),
        );
    }
}
