<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integration\Enums\IntegrationProvider;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare_oauth.enabled' => true,
            'services.cloudflare_oauth.client_id' => 'cloudflare-client-id',
            'services.cloudflare_oauth.client_secret' => 'cloudflare-client-secret',
            'services.cloudflare_oauth.authorization_endpoint' => 'https://dash.cloudflare.com/oauth2/auth',
            'services.cloudflare_oauth.token_endpoint' => 'https://dash.cloudflare.com/oauth2/token',
            'services.cloudflare_oauth.revoke_endpoint' => 'https://dash.cloudflare.com/oauth2/revoke',
            'services.cloudflare_oauth.scopes' => [
                'account-settings.read',
                'zone.read',
                'dns.read',
                'dns.write',
                'offline_access',
            ],
            'services.cloudflare_oauth.connect_timeout' => 5,
            'services.cloudflare_oauth.timeout' => 10,
        ]);
    }

    public function test_integration_routes_require_authentication(): void
    {
        $this->get(
            route('panel.integrations.index'),
        )->assertRedirect(route('login'));

        $this->get(
            route('panel.integrations.cloudflare.redirect'),
        )->assertRedirect(route('login'));

        $this->get(
            route(
                'panel.integrations.cloudflare.callback',
                [
                    'state' => 'state',
                    'code' => 'code',
                ],
            ),
        )->assertRedirect(route('login'));
    }

    public function test_cloudflare_redirect_starts_user_bound_pkce_attempt(): void
    {
        $user = User::factory()->create();

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

        self::assertSame(
            'https://dash.cloudflare.com/oauth2/auth',
            strtok($location, '?'),
        );
        self::assertSame(
            'cloudflare-client-id',
            $query['client_id'] ?? null,
        );
        self::assertSame(
            'account-settings.read zone.read dns.read dns.write offline_access',
            $query['scope'] ?? null,
        );
        self::assertSame(
            route('panel.integrations.cloudflare.callback'),
            $query['redirect_uri'] ?? null,
        );
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
        self::assertNotEmpty($query['state'] ?? null);

        $attempt = session('integrations.cloudflare.oauth');

        self::assertIsArray($attempt);
        self::assertSame(
            $user->getKey(),
            $attempt['user_id'] ?? null,
        );
        self::assertSame(
            hash('sha256', (string) $query['state']),
            $attempt['state_hash'] ?? null,
        );
        self::assertArrayNotHasKey('state', $attempt);

        $codeVerifier = $attempt['code_verifier'] ?? null;
        self::assertIsString($codeVerifier);
        self::assertGreaterThanOrEqual(43, strlen($codeVerifier));
        self::assertLessThanOrEqual(128, strlen($codeVerifier));
    }

    public function test_callback_persists_encrypted_cloudflare_connection(): void
    {
        $user = User::factory()->create();
        $state = $this->startAttempt($user);

        Http::fake([
            'https://dash.cloudflare.com/oauth2/token' => Http::response(
                [
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'scope' => 'account-settings.read zone.read dns.read dns.write offline_access',
                    'token_type' => 'Bearer',
                ],
                200,
            ),
        ]);

        $this->actingAs($user)
            ->get(
                route(
                    'panel.integrations.cloudflare.callback',
                    [
                        'state' => $state,
                        'code' => 'authorization-code',
                    ],
                ),
            )
            ->assertRedirect(route('panel.integrations.index'))
            ->assertSessionHas(
                'integration_status',
                'Cloudflare با موفقیت به حساب شما متصل شد.',
            );

        $connection = IntegrationConnection::query()->sole();

        self::assertSame($user->getKey(), $connection->user_id);
        self::assertSame(
            IntegrationProvider::Cloudflare,
            $connection->provider,
        );
        self::assertSame('access-token', $connection->access_token);
        self::assertSame('refresh-token', $connection->refresh_token);
        self::assertSame(
            $this->fullScopes(),
            $connection->scopes,
        );
        self::assertNotNull($connection->access_token_expires_at);
        self::assertNotNull($connection->connected_at);

        $raw = DB::table('integration_connections')
            ->where('id', $connection->getKey())
            ->first();

        self::assertNotNull($raw);
        self::assertNotSame('access-token', $raw->access_token);
        self::assertNotSame('refresh-token', $raw->refresh_token);
    }

    public function test_invalid_callback_state_does_not_persist_connection(): void
    {
        $user = User::factory()->create();
        $this->startAttempt($user);

        Http::fake();

        $this->actingAs($user)
            ->get(
                route(
                    'panel.integrations.cloudflare.callback',
                    [
                        'state' => 'tampered-state',
                        'code' => 'authorization-code',
                    ],
                ),
            )
            ->assertRedirect(route('panel.integrations.index'))
            ->assertSessionHas(
                'integration_error',
                'درخواست اتصال Cloudflare معتبر نیست یا منقضی شده است.',
            );

        self::assertDatabaseCount('integration_connections', 0);
        Http::assertNothingSent();
    }

    public function test_reconnecting_replaces_tokens_without_creating_duplicate_connection(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'old-access-token',
            'refresh_token' => 'old-refresh-token',
            'scopes' => ['account.read'],
            'connected_at' => now()->subDay(),
        ]);

        $state = $this->startAttempt($user);

        Http::fake([
            'https://dash.cloudflare.com/oauth2/token' => Http::response(
                [
                    'access_token' => 'new-access-token',
                    'refresh_token' => 'new-refresh-token',
                    'expires_in' => 7200,
                    'scope' => 'account-settings.read zone.read dns.read dns.write offline_access',
                ],
                200,
            ),
            'https://dash.cloudflare.com/oauth2/revoke' => Http::response(
                [],
                200,
            ),
        ]);

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
            ->assertRedirect(route('panel.integrations.index'));

        self::assertDatabaseCount('integration_connections', 1);

        $connection = IntegrationConnection::query()->sole();
        self::assertSame('new-access-token', $connection->access_token);
        self::assertSame('new-refresh-token', $connection->refresh_token);
        self::assertSame(
            $this->fullScopes(),
            $connection->scopes,
        );

        Http::assertSent(
            static fn ($request): bool => $request->url()
                === 'https://dash.cloudflare.com/oauth2/revoke'
                && $request['token'] === 'old-refresh-token',
        );
        Http::assertSent(
            static fn ($request): bool => $request->url()
                === 'https://dash.cloudflare.com/oauth2/revoke'
                && $request['token'] === 'old-access-token',
        );
    }

    public function test_disconnect_revokes_tokens_and_removes_local_connection(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => $this->fullScopes(),
            'connected_at' => now(),
        ]);

        Http::fake([
            'https://dash.cloudflare.com/oauth2/revoke' => Http::response(
                [],
                200,
            ),
        ]);

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
        Http::assertSent(
            static fn ($request): bool => $request['token']
                === 'refresh-token',
        );
        Http::assertSent(
            static fn ($request): bool => $request['token']
                === 'access-token',
        );
    }

    public function test_integration_page_never_renders_stored_tokens(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'never-render-access-token',
            'refresh_token' => 'never-render-refresh-token',
            'scopes' => $this->fullScopes(),
            'connected_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('panel.integrations.index'))
            ->assertOk()
            ->assertSee('Cloudflare')
            ->assertSee('ورود به Cloudflare')
            ->assertSee('جزئیات اتصال')
            ->assertDontSee('never-render-access-token')
            ->assertDontSee('never-render-refresh-token');
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

    /** @return list<string> */
    private function fullScopes(): array
    {
        return [
            'account-settings.read',
            'zone.read',
            'dns.read',
            'dns.write',
            'offline_access',
        ];
    }
}
