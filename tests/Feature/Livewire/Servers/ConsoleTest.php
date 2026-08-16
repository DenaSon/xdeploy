<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\DTOs\CloudServerConsoleData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Livewire\Servers\Console;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_console_page(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer($user);

        $this->get(
            route('panel.servers.console', $server),
        )->assertRedirect(
            route('login'),
        );
    }

    public function test_user_cannot_open_another_users_console_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $server = $this->cloudServer($owner);

        $this->actingAs($other)
            ->get(
                route('panel.servers.console', $server),
            )
            ->assertNotFound();
    }

    public function test_user_provided_server_cannot_open_console_page(): void
    {
        $user = User::factory()->create();
        $server = $this->userProvidedServer($user);

        $this->actingAs($user)
            ->get(
                route('panel.servers.console', $server),
            )
            ->assertNotFound();
    }

    public function test_provider_without_console_capability_cannot_open_console_page(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            provider: CloudProviderType::Liara,
            region: 'iran',
            serverId: 'liara-vm-123',
        );

        $this->bindConsoleCapability(
            provider: CloudProviderType::Liara,
            supported: false,
        );

        $this->actingAs($user)
            ->get(
                route('panel.servers.console', $server),
            )
            ->assertNotFound();
    }

    public function test_workspace_hides_console_navigation_for_unsupported_provider(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer(
            user: $user,
            provider: CloudProviderType::Liara,
            region: 'iran',
            serverId: 'liara-vm-123',
        );

        $this->bindConsoleCapability(
            provider: CloudProviderType::Liara,
            supported: false,
        );

        $this->actingAs($user)
            ->get(
                route('panel.servers.details', $server),
            )
            ->assertOk()
            ->assertDontSee('کنسول');
    }

    public function test_workspace_shows_console_navigation_for_supported_provider(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer($user);

        $this->bindConsoleCapability(
            provider: CloudProviderType::Arvan,
            supported: true,
        );

        $this->actingAs($user)
            ->get(
                route('panel.servers.details', $server),
            )
            ->assertOk()
            ->assertSee('کنسول');
    }

    public function test_owner_can_load_cloud_server_console(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer($user);
        $url = 'https://console.example/vnc?token=test-token';

        $console = Mockery::mock(
            CloudServerConsoleInterface::class,
        );

        $console
            ->shouldReceive('getVncConsole')
            ->once()
            ->with(
                'ir-thr-ba1',
                'cloud-server-123',
            )
            ->andReturn(
                new CloudServerConsoleData(
                    url: $url,
                ),
            );

        $this->bindConsoleCapability(
            provider: CloudProviderType::Arvan,
            supported: true,
            console: $console,
        );

        $this->actingAs($user);

        Livewire::test(
            Console::class,
            ['server' => $server],
        )
            ->call('loadConsole')
            ->assertSet('consoleUrl', $url)
            ->assertSet('consoleError', null)
            ->assertSee('کنسول سرور');
    }

    public function test_provider_failure_is_rendered_as_console_error_state(): void
    {
        $user = User::factory()->create();
        $server = $this->cloudServer($user);

        $console = Mockery::mock(
            CloudServerConsoleInterface::class,
        );

        $console
            ->shouldReceive('getVncConsole')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Provider unavailable.',
                ),
            );

        $this->bindConsoleCapability(
            provider: CloudProviderType::Arvan,
            supported: true,
            console: $console,
        );

        $this->actingAs($user);

        Livewire::test(
            Console::class,
            ['server' => $server],
        )
            ->call('loadConsole')
            ->assertSet('consoleUrl', null)
            ->assertSet(
                'consoleError',
                'برقراری اتصال به کنسول سرور ناموفق بود.',
            )
            ->assertSee('اتصال به کنسول برقرار نشد')
            ->assertSee('تلاش مجدد');
    }

    private function bindConsoleCapability(
        CloudProviderType $provider,
        bool $supported,
        ?CloudServerConsoleInterface $console = null,
    ): void {
        $registry = Mockery::mock(
            CloudProviderRegistryInterface::class,
        );

        $registry
            ->shouldReceive('supportsCapability')
            ->with(
                $provider,
                CloudServerConsoleInterface::class,
            )
            ->andReturn($supported);

        if ($console instanceof CloudServerConsoleInterface) {
            $registry
                ->shouldReceive('resolveCapability')
                ->with(
                    $provider,
                    CloudServerConsoleInterface::class,
                )
                ->andReturn($console);
        }

        $this->app->instance(
            CloudProviderRegistryInterface::class,
            $registry,
        );
    }

    private function cloudServer(
        User $user,
        CloudProviderType $provider = CloudProviderType::Arvan,
        string $region = 'ir-thr-ba1',
        string $serverId = 'cloud-server-123',
    ): Server {
        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'cloud-vps',
            'host' => '203.0.113.50',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => $provider->value,
            'cloud_server_id' => $serverId,
            'cloud_region' => $region,
        ]);
    }

    private function userProvidedServer(User $user): Server
    {
        return Server::query()->create([
            'user_id' => $user->id,
            'name' => 'user-vps',
            'host' => '203.0.113.60',
            'port' => 22,
            'username' => 'root',
            'authentication_type' => AuthenticationType::Password,
            'credential' => null,
            'status' => ServerStatus::Active,
            'cloud_provider' => null,
            'cloud_server_id' => null,
            'cloud_region' => null,
        ]);
    }
}
