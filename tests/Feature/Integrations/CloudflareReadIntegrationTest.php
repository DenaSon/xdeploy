<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integration\Enums\IntegrationProvider;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareReadIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $zoneId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zoneId = str_repeat('c', 32);

        config([
            'services.cloudflare_oauth.client_id' => 'cloudflare-client-id',
            'services.cloudflare_oauth.client_secret' => 'cloudflare-client-secret',
            'services.cloudflare_oauth.authorization_endpoint' => 'https://dash.cloudflare.com/oauth2/auth',
            'services.cloudflare_oauth.token_endpoint' => 'https://dash.cloudflare.com/oauth2/token',
            'services.cloudflare_oauth.revoke_endpoint' => 'https://dash.cloudflare.com/oauth2/revoke',
            'services.cloudflare_oauth.scopes' => [
                'account.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            'services.cloudflare_oauth.connect_timeout' => 5,
            'services.cloudflare_oauth.timeout' => 10,
            'services.cloudflare_api.base_url' => 'https://api.cloudflare.com/client/v4',
            'services.cloudflare_api.connect_timeout' => 5,
            'services.cloudflare_api.timeout' => 15,
            'services.cloudflare_api.max_pages' => 20,
        ]);
    }

    public function test_cloudflare_read_page_requires_authentication(): void
    {
        $this->get(
            route('panel.integrations.cloudflare.overview'),
        )->assertRedirect(route('login'));
    }

    public function test_legacy_connection_requires_scope_upgrade_without_remote_read(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'legacy-access-token',
            'refresh_token' => 'legacy-refresh-token',
            'scopes' => [
                'account.read',
                'offline_access',
            ],
            'connected_at' => now(),
        ]);

        Http::fake();

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.overview'))
            ->assertOk()
            ->assertSee('دسترسی Cloudflare باید به‌روزرسانی شود')
            ->assertSee('zone.read')
            ->assertSee('dns.read')
            ->assertDontSee('legacy-access-token')
            ->assertDontSee('legacy-refresh-token');

        Http::assertNothingSent();
    }

    public function test_read_page_loads_accounts_zones_and_dns_without_write_requests(): void
    {
        $user = User::factory()->create();

        $connection = IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'read-access-token',
            'refresh_token' => 'read-refresh-token',
            'scopes' => [
                'account.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            'access_token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        $this->fakeReadApi();

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.overview'))
            ->assertOk()
            ->assertSee('Primary Account')
            ->assertSee('example.com')
            ->assertSee('app.example.com')
            ->assertSee('203.0.113.10')
            ->assertDontSee('read-access-token')
            ->assertDontSee('read-refresh-token');

        self::assertNotNull(
            $connection->fresh()?->last_synced_at,
        );

        Http::assertSent(
            static fn ($request): bool => str_starts_with(
                $request->url(),
                'https://api.cloudflare.com/client/v4/',
            ) && $request->method() === 'GET',
        );

        Http::assertNotSent(
            static fn ($request): bool => str_starts_with(
                $request->url(),
                'https://api.cloudflare.com/client/v4/',
            ) && $request->method() !== 'GET',
        );
    }

    public function test_expired_access_token_is_refreshed_before_cloudflare_reads(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'expired-access-token',
            'refresh_token' => 'old-refresh-token',
            'scopes' => [
                'account.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            'access_token_expires_at' => now()->subMinute(),
            'connected_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://dash.cloudflare.com/oauth2/token' => Http::response([
                'access_token' => 'fresh-access-token',
                'refresh_token' => 'fresh-refresh-token',
                'expires_in' => 3600,
                'scope' => 'account.read zone.read dns.read offline_access',
                'token_type' => 'Bearer',
            ]),
            'https://api.cloudflare.com/client/v4/accounts*' => Http::response(
                $this->accountsResponse(),
            ),
            'https://api.cloudflare.com/client/v4/zones?*' => Http::response(
                $this->zonesResponse(),
            ),
            "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records?*" => Http::response(
                $this->dnsResponse(),
            ),
        ]);

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.overview'))
            ->assertOk()
            ->assertSee('example.com');

        $connection = IntegrationConnection::query()->sole();

        self::assertSame(
            'fresh-access-token',
            $connection->access_token,
        );
        self::assertSame(
            'fresh-refresh-token',
            $connection->refresh_token,
        );
        self::assertTrue(
            $connection->access_token_expires_at?->isFuture() ?? false,
        );

        Http::assertSent(
            static fn ($request): bool => $request->url()
                === 'https://dash.cloudflare.com/oauth2/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'old-refresh-token',
        );

        Http::assertSent(
            static fn ($request): bool => str_starts_with(
                $request->url(),
                'https://api.cloudflare.com/client/v4/',
            ) && $request->hasHeader(
                'Authorization',
                'Bearer fresh-access-token',
            ),
        );
    }

    public function test_forbidden_cloudflare_read_requests_surface_reconnect_state(): void
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => [
                'account.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            'access_token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts*' => Http::response(
                [],
                403,
            ),
        ]);

        $this->actingAs($user)
            ->get(route('panel.integrations.cloudflare.overview'))
            ->assertOk()
            ->assertSee('دسترسی Cloudflare باید به‌روزرسانی شود')
            ->assertSee('به‌روزرسانی دسترسی');
    }

    private function fakeReadApi(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts*' => Http::response(
                $this->accountsResponse(),
            ),
            'https://api.cloudflare.com/client/v4/zones?*' => Http::response(
                $this->zonesResponse(),
            ),
            "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records?*" => Http::response(
                $this->dnsResponse(),
            ),
        ]);

        Http::assertSentCount(0);
    }

    /** @return array<string, mixed> */
    private function accountsResponse(): array
    {
        return [
            'success' => true,
            'result' => [
                [
                    'id' => str_repeat('a', 32),
                    'name' => 'Primary Account',
                ],
            ],
            'result_info' => ['total_pages' => 1],
        ];
    }

    /** @return array<string, mixed> */
    private function zonesResponse(): array
    {
        return [
            'success' => true,
            'result' => [
                [
                    'id' => $this->zoneId,
                    'name' => 'example.com',
                    'status' => 'active',
                    'paused' => false,
                    'type' => 'full',
                    'development_mode' => 0,
                    'account' => [
                        'id' => str_repeat('a', 32),
                        'name' => 'Primary Account',
                    ],
                    'name_servers' => [
                        'a.ns.cloudflare.com',
                        'b.ns.cloudflare.com',
                    ],
                ],
            ],
            'result_info' => ['total_pages' => 1],
        ];
    }

    /** @return array<string, mixed> */
    private function dnsResponse(): array
    {
        return [
            'success' => true,
            'result' => [
                [
                    'id' => str_repeat('d', 32),
                    'type' => 'A',
                    'name' => 'app.example.com',
                    'content' => '203.0.113.10',
                    'proxied' => true,
                    'ttl' => 1,
                    'modified_on' => '2026-08-18T00:00:00Z',
                ],
            ],
            'result_info' => ['total_pages' => 1],
        ];
    }
}
