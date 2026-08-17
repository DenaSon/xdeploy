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
            'services.cloudflare_oauth.authorization_endpoint' => 'https://dash.cloudflare.com/oauth2/auth',
            'services.cloudflare_oauth.token_endpoint' => 'https://dash.cloudflare.com/oauth2/token',
            'services.cloudflare_oauth.revoke_endpoint' => 'https://dash.cloudflare.com/oauth2/revoke',
            'services.cloudflare_oauth.scopes' => [
                'account-settings.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
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

    public function test_reconnect_keeps_old_connection_when_old_tokens_cannot_be_revoked(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection($user);
        $state = $this->startAttempt($user);

        Http::fakeSequence()
            ->push([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
                'scope' => 'account-settings.read zone.read dns.read offline_access',
            ], 200)
            ->push([], 503)
            ->push([], 503)
            ->push([], 200)
            ->push([], 200);

        $this->actingAs($user)
            ->get(
                route(
                    'panel.integrations.cloudflare.callback',
                    [
                        'state' => $state,
                        'code' => 'new-code',
                    ],
                ),
            )
            ->assertRedirect(route('panel.integrations.index'))
            ->assertSessionHas(
                'integration_error',
                'اتصال قبلی Cloudflare قابل لغو نبود؛ برای جلوگیری از باقی‌ماندن دسترسی بدون کنترل، اتصال جدید ذخیره نشد. دوباره تلاش کنید.',
            );

        $connection->refresh();

        self::assertSame('access-token', $connection->access_token);
        self::assertSame('refresh-token', $connection->refresh_token);
        self::assertDatabaseCount('integration_connections', 1);
        Http::assertSentCount(5);
    }

    private function connection(User $user): IntegrationConnection
    {
        return IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => [
                'account-settings.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            'connected_at' => now(),
        ]);
    }

    private function startAttempt(User $user): string
    {
        $response = $this->actingAs($user)
            ->get(
                route('panel.integrations.cloudflare.redirect'),
            )
            ->assertRedirect();

        $location = $response->headers->get('Location');
        self::assertIsString($location);

        parse_str(
            (string) parse_url($location, PHP_URL_QUERY),
            $query,
        );

        $state = $query['state'] ?? null;
        self::assertIsString($state);
        self::assertNotSame('', $state);

        return $state;
    }
}
