<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\PublicEndpoints;

use App\Application\PublicEndpoint\Contracts\PublicEndpointDriverInterface;
use App\Application\PublicEndpoint\DTOs\PublicEndpointApplicationStatus;
use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointDnsPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointPreflightResult;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointRuntimeInfo;
use App\Domain\PublicEndpoint\DTOs\PublicEndpointServerPreflightResult;
use App\Domain\PublicEndpoint\Enums\PublicEndpointRuntimeState;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Livewire\PublicEndpoints\Setup;
use App\Models\PublicEndpoint;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_setup_persists_pending_endpoint_and_activates_it(): void
    {
        $user = $this->createUser('09173432401');
        $server = $this->createServer($user);
        $driver = new SetupFakePublicEndpointDriver(ApplicationType::N8n);

        $this->bindDriver($driver);

        Livewire::actingAs($user)
            ->test(Setup::class, [
                'serverId' => $server->getKey(),
                'applicationType' => ApplicationType::N8n->value,
                'applicationName' => 'n8n',
            ])
            ->set('domain', ' AUTOMATION.EXAMPLE.COM. ')
            ->call('runPreflight')
            ->assertSet('domain', 'automation.example.com')
            ->assertSet('dnsPreflight.ready', true)
            ->assertSet('serverPreflight.ready', true)
            ->call('activateEndpoint')
            ->assertHasNoErrors();

        $endpoint = PublicEndpoint::query()->firstOrFail();

        $this->assertSame(
            ApplicationType::N8n,
            $endpoint->application_type,
        );
        $this->assertSame(
            'automation.example.com',
            $endpoint->domain,
        );
        $this->assertNotNull($endpoint->activated_at);
        $this->assertSame(1, $driver->preflightCalls);
        $this->assertSame(1, $driver->enableCalls);
    }

    public function test_active_endpoint_cannot_be_replaced_from_setup_flow(): void
    {
        $user = $this->createUser('09173432402');
        $server = $this->createServer($user);
        $driver = new SetupFakePublicEndpointDriver(ApplicationType::N8n);

        $this->bindDriver($driver);

        $endpoint = PublicEndpoint::query()->create([
            'server_id' => $server->getKey(),
            'application_type' => ApplicationType::N8n,
            'domain' => 'current.example.com',
            'activated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(Setup::class, [
                'serverId' => $server->getKey(),
                'applicationType' => ApplicationType::N8n->value,
                'applicationName' => 'n8n',
                'endpointId' => $endpoint->getKey(),
            ])
            ->set('domain', 'new.example.com')
            ->call('runPreflight')
            ->assertSet(
                'activationError',
                'برای این برنامه از قبل یک دامنه فعال ثبت شده است.',
            );

        $this->assertDatabaseHas('public_endpoints', [
            'id' => $endpoint->getKey(),
            'domain' => 'current.example.com',
        ]);
        $this->assertSame(0, $driver->preflightCalls);
    }

    private function bindDriver(
        SetupFakePublicEndpointDriver $driver,
    ): void {
        $this->app->instance(
            PublicEndpointDriverRegistry::class,
            new PublicEndpointDriverRegistry([$driver]),
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
            'name' => 'endpoint-setup-test',
            'host' => '192.0.2.90',
            'port' => 22,
            'username' => 'root',
        ]);
    }
}

final class SetupFakePublicEndpointDriver implements PublicEndpointDriverInterface
{
    public int $preflightCalls = 0;

    public int $enableCalls = 0;

    public function __construct(
        private readonly ApplicationType $applicationType,
    ) {}

    public function type(): ApplicationType
    {
        return $this->applicationType;
    }

    public function name(): string
    {
        return $this->applicationType === ApplicationType::N8n
            ? 'n8n'
            : 'Marzban';
    }

    public function description(): string
    {
        return 'Test endpoint driver.';
    }

    public function icon(): string
    {
        return 'lucide.box';
    }

    public function openUrl(PublicEndpointDomain $domain): string
    {
        return "https://{$domain->value}/";
    }

    public function status(
        User $user,
        Server $server,
    ): PublicEndpointApplicationStatus {
        return $this->statusResult(
            PublicEndpointRuntimeState::Disabled,
        );
    }

    public function preflight(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointPreflightResult {
        $this->preflightCalls++;

        return new PublicEndpointPreflightResult(
            dns: new PublicEndpointDnsPreflightResult(
                domain: $domain->value,
                serverIpv4Address: '203.0.113.10',
                resolvedIpv4Addresses: ['203.0.113.10'],
                resolvedIpv6Addresses: [],
            ),
            server: new PublicEndpointServerPreflightResult(
                layoutState: 'supported',
                layoutSupported: true,
                managedCaddyDetected: true,
                hasPortConflict: false,
                ready: true,
                ports: [
                    80 => [
                        'port' => 80,
                        'state' => 'managed',
                        'owner' => 'xdeploy_caddy',
                        'available_for_xdeploy' => true,
                        'has_conflict' => false,
                    ],
                    443 => [
                        'port' => 443,
                        'state' => 'managed',
                        'owner' => 'xdeploy_caddy',
                        'available_for_xdeploy' => true,
                        'has_conflict' => false,
                    ],
                ],
            ),
        );
    }

    public function enable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
        $this->enableCalls++;

        return $this->statusResult(
            state: PublicEndpointRuntimeState::Enabled,
            domain: $domain->value,
        );
    }

    public function disable(
        User $user,
        Server $server,
        PublicEndpointDomain $domain,
    ): PublicEndpointApplicationStatus {
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
