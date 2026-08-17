<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Application\Integrations\Cloudflare\CloudflareZoneService;
use App\Domain\Integration\Enums\IntegrationProvider;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareZoneFoundationTest extends TestCase
{
    use RefreshDatabase;

    private string $accountId;

    private string $zoneId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountId = str_repeat('a', 32);
        $this->zoneId = str_repeat('c', 32);

        config([
            'services.cloudflare_api.base_url' => 'https://api.cloudflare.com/client/v4',
            'services.cloudflare_api.connect_timeout' => 5,
            'services.cloudflare_api.timeout' => 15,
            'services.cloudflare_api.max_pages' => 20,
        ]);
    }

    public function test_zone_can_be_created_for_an_accessible_account(): void
    {
        $connection = $this->connection();
        $accountId = $this->accountId;
        $zoneId = $this->zoneId;

        Http::fake(static function (Request $request) use (
            $accountId,
            $zoneId,
        ) {
            if (
                $request->method() === 'GET'
                && str_starts_with(
                    $request->url(),
                    'https://api.cloudflare.com/client/v4/accounts?',
                )
            ) {
                return Http::response([
                    'success' => true,
                    'result' => [[
                        'id' => $accountId,
                        'name' => 'Primary Account',
                    ]],
                    'result_info' => ['total_pages' => 1],
                ]);
            }

            if (
                $request->method() === 'POST'
                && $request->url() === 'https://api.cloudflare.com/client/v4/zones'
            ) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => $zoneId,
                        'name' => 'example.com',
                        'status' => 'pending',
                        'paused' => false,
                        'type' => 'full',
                        'account' => [
                            'id' => $accountId,
                            'name' => 'Primary Account',
                        ],
                        'name_servers' => [
                            'alice.ns.cloudflare.com',
                            'bob.ns.cloudflare.com',
                        ],
                    ],
                ]);
            }

            return Http::response([], 500);
        });

        $zone = app(CloudflareZoneService::class)
            ->create(
                connection: $connection,
                accountId: $this->accountId,
                domain: ' Example.COM. ',
            );

        self::assertSame($this->zoneId, $zone['id']);
        self::assertSame('example.com', $zone['name']);
        self::assertSame('pending', $zone['status']);
        self::assertSame(
            ['alice.ns.cloudflare.com', 'bob.ns.cloudflare.com'],
            $zone['name_servers'],
        );

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.cloudflare.com/client/v4/zones'
            && $request['account']['id'] === $accountId
            && $request['name'] === 'example.com'
            && $request['type'] === 'full');
    }

    public function test_zone_create_rejects_account_outside_the_oauth_connection(): void
    {
        $connection = $this->connection();
        $accessibleAccountId = $this->accountId;

        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts*' => Http::response([
                'success' => true,
                'result' => [[
                    'id' => $accessibleAccountId,
                    'name' => 'Primary Account',
                ]],
                'result_info' => ['total_pages' => 1],
            ]),
        ]);

        try {
            app(CloudflareZoneService::class)
                ->create(
                    connection: $connection,
                    accountId: str_repeat('b', 32),
                    domain: 'example.com',
                );

            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(
                CloudflareApiException::INVALID_REQUEST,
                $exception->reason,
            );
        }

        Http::assertNotSent(
            static fn (Request $request): bool => $request->method() === 'POST'
                && str_ends_with($request->url(), '/zones'),
        );
    }

    public function test_zone_mutation_is_blocked_without_zone_write_scope(): void
    {
        $connection = $this->connection([
            'account-settings.read',
            'zone.read',
            'dns.read',
            'dns.write',
            'offline_access',
        ]);

        Http::fake();

        self::assertFalse(
            app(CloudflareZoneService::class)
                ->manageable($connection),
        );

        try {
            app(CloudflareZoneService::class)
                ->create(
                    connection: $connection,
                    accountId: $this->accountId,
                    domain: 'example.com',
                );

            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(
                CloudflareApiException::MISSING_SCOPES,
                $exception->reason,
            );
        }

        Http::assertNothingSent();
    }

    public function test_zone_refresh_reads_current_lifecycle_state(): void
    {
        $connection = $this->connection();
        $zoneId = $this->zoneId;
        $accountId = $this->accountId;

        Http::fake([
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}" => Http::response([
                'success' => true,
                'result' => [
                    'id' => $zoneId,
                    'name' => 'example.com',
                    'status' => 'active',
                    'paused' => false,
                    'type' => 'full',
                    'account' => [
                        'id' => $accountId,
                        'name' => 'Primary Account',
                    ],
                    'name_servers' => [
                        'alice.ns.cloudflare.com',
                        'bob.ns.cloudflare.com',
                    ],
                    'activated_on' => '2026-08-18T01:00:00Z',
                ],
            ]),
        ]);

        $zone = app(CloudflareZoneService::class)
            ->refresh($connection, $zoneId);

        self::assertSame('active', $zone['status']);
        self::assertSame(
            '2026-08-18T01:00:00Z',
            $zone['activated_on'],
        );
    }

    public function test_zone_delete_resolves_zone_before_destructive_mutation(): void
    {
        $connection = $this->connection();
        $zoneId = $this->zoneId;
        $accountId = $this->accountId;
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}";
        $methods = [];

        Http::fake(static function (Request $request) use (
            &$methods,
            $url,
            $zoneId,
            $accountId,
        ) {
            if ($request->url() !== $url) {
                return Http::response([], 500);
            }

            $methods[] = $request->method();

            if ($request->method() === 'GET') {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => $zoneId,
                        'name' => 'example.com',
                        'status' => 'pending',
                        'paused' => false,
                        'type' => 'full',
                        'account' => [
                            'id' => $accountId,
                            'name' => 'Primary Account',
                        ],
                        'name_servers' => [
                            'alice.ns.cloudflare.com',
                            'bob.ns.cloudflare.com',
                        ],
                    ],
                ]);
            }

            if ($request->method() === 'DELETE') {
                return Http::response([
                    'success' => true,
                    'result' => ['id' => $zoneId],
                ]);
            }

            return Http::response([], 500);
        });

        app(CloudflareZoneService::class)
            ->delete($connection, $zoneId);

        self::assertSame(['GET', 'DELETE'], $methods);
    }

    /**
     * @param list<string>|null $scopes
     */
    private function connection(?array $scopes = null): IntegrationConnection
    {
        $user = User::factory()->create();

        return IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => $scopes ?? [
                'account-settings.read',
                'zone.read',
                'zone.write',
                'dns.read',
                'dns.write',
                'offline_access',
            ],
            'access_token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);
    }
}
