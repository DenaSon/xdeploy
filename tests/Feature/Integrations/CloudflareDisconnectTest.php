<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integration\Enums\IntegrationProvider;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareDisconnectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare_oauth.client_id' => 'cloudflare-client-id',
            'services.cloudflare_oauth.client_secret' => 'cloudflare-client-secret',
            'services.cloudflare_oauth.revoke_endpoint' => 'https://dash.cloudflare.com/oauth2/revoke',
            'services.cloudflare_oauth.connect_timeout' => 5,
            'services.cloudflare_oauth.timeout' => 10,
        ]);
    }

    public function test_disconnect_succeeds_when_at_least_one_revocation_is_confirmed(): void
    {
        $user = User::factory()->create();
        $this->connection($user);

        Http::fakeSequence()
            ->push([], 400)
            ->push([], 200);

        $this->actingAs($user)
            ->delete(
                route('panel.integrations.cloudflare.disconnect'),
            )
            ->assertRedirect(route('panel.integrations.index'))
            ->assertSessionHas(
                'integration_status',
                'اتصال Cloudflare با موفقیت قطع شد.',
            );

        self::assertDatabaseCount('integration_connections', 0);
        Http::assertSentCount(2);
    }

    public function test_disconnect_removes_local_connection_when_remote_revocation_cannot_be_confirmed(): void
    {
        $user = User::factory()->create();
        $this->connection($user);

        Http::fake([
            'https://dash.cloudflare.com/oauth2/revoke' => Http::response(
                [],
                503,
            ),
        ]);

        $this->actingAs($user)
            ->delete(
                route('panel.integrations.cloudflare.disconnect'),
            )
            ->assertRedirect(route('panel.integrations.index'))
            ->assertSessionHas(
                'integration_error',
                'اتصال از Coreflare حذف شد، اما لغو دسترسی در Cloudflare تأیید نشد. مجوز Coreflare را در Cloudflare نیز بررسی کنید.',
            );

        self::assertDatabaseCount('integration_connections', 0);
        Http::assertSentCount(2);
    }

    private function connection(User $user): IntegrationConnection
    {
        return IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => ['account.read', 'offline_access'],
            'connected_at' => now(),
        ]);
    }
}
