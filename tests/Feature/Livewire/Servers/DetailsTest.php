<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Servers;

use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\ServerStatus;
use App\Infrastructure\Security\Encryption\CredentialKeyRing;
use App\Infrastructure\Security\Encryption\ServerCredentialCipher;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DetailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCredentialEncryption();
    }

    public function test_guest_cannot_open_server_details_page(): void
    {
        $server = $this->createServer(
            user: $this->createUser('09120000001'),
            credential: 'guest-hidden-secret',
        );

        $this
            ->get(
                route(
                    'panel.servers.details',
                    $server,
                ),
            )
            ->assertRedirect(
                route('login'),
            );
    }

    public function test_user_cannot_open_another_users_server_details_page(): void
    {
        $owner = $this->createUser('09120000002');
        $attacker = $this->createUser('09120000003');

        $server = $this->createServer(
            user: $owner,
            credential: 'owner-only-secret',
        );

        $this
            ->actingAs($attacker)
            ->get(
                route(
                    'panel.servers.details',
                    $server,
                ),
            )
            ->assertNotFound();
    }

    public function test_owner_sees_connection_details_with_actual_ssh_username_but_not_password(): void
    {
        $user = $this->createUser('09120000004');

        $server = $this->createServer(
            user: $user,
            credential: 'never-render-this-password',
            username: 'ubuntu',
            host: '203.0.113.25',
            port: 2222,
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.details',
                    $server,
                ),
            )
            ->assertOk()
            ->assertSee('اطلاعات اتصال')
            ->assertSee('203.0.113.25')
            ->assertSee('ubuntu')
            ->assertSee('ssh ubuntu@203.0.113.25 -p 2222')
            ->assertDontSee('never-render-this-password');
    }

    public function test_cloud_infrastructure_metadata_is_hidden_from_owner(): void
    {
        $user = $this->createUser('09120000005');

        $server = $this->createServer(
            user: $user,
            credential: 'cloud-secret',
            cloud: true,
        );

        $this
            ->actingAs($user)
            ->get(
                route(
                    'panel.servers.details',
                    $server,
                ),
            )
            ->assertOk()
            ->assertSee('مشخصات سرور')
            ->assertSee('خرید از xDeploy')
            ->assertSee('شروع سرویس')
            ->assertSee('پایان سرویس')
            ->assertDontSee('اطلاعات سرویس ابری')
            ->assertDontSee('ArvanCloud')
            ->assertDontSee('eu-west1-a')
            ->assertDontSee('provider-server-123');
    }

    private function createUser(string $phone): User
    {
        return User::query()->create([
            'name' => 'Server Details User',
            'phone' => $phone,
        ]);
    }

    private function createServer(
        User $user,
        string $credential,
        string $username = 'root',
        string $host = '192.0.2.10',
        int $port = 22,
        bool $cloud = false,
    ): Server {
        return $user
            ->servers()
            ->create([
                'name' => 'Server Details Test',
                'host' => $host,
                'port' => $port,
                'username' => $username,
                'authentication_type' => AuthenticationType::Password,
                'credential' => $credential,
                'status' => ServerStatus::Active,
                'cloud_provider' => $cloud ? 'arvan' : null,
                'cloud_server_id' => $cloud ? 'provider-server-123' : null,
                'cloud_region' => $cloud ? 'eu-west1-a' : null,
                'provisioned_at' => $cloud ? now()->subDay() : null,
                'expires_at' => $cloud ? now()->addDays(13) : null,
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
