<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integration\Enums\IntegrationProvider;
use App\Livewire\Integrations\Cloudflare\Zones;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

final class CloudflareZoneOnboardingTest extends TestCase
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
            'services.cloudflare_oauth.client_id' => 'cloudflare-client-id',
            'services.cloudflare_oauth.client_secret' => 'cloudflare-client-secret',
            'services.cloudflare_oauth.scopes' => $this->fullScopes(),
            'services.cloudflare_api.base_url' => 'https://api.cloudflare.com/client/v4',
            'services.cloudflare_api.connect_timeout' => 5,
            'services.cloudflare_api.timeout' => 15,
            'services.cloudflare_api.max_pages' => 20,
        ]);
    }

    public function test_zone_can_be_created_and_pending_nameservers_are_shown(): void
    {
        $user = $this->userWithConnection();
        $accountId = $this->accountId;
        $zoneId = $this->zoneId;
        $created = false;

        Http::fake(function (Request $request) use (
            $accountId,
            $zoneId,
            &$created,
        ) {
            if ($this->isAccountsRequest($request)) {
                return $this->accountsResponse($accountId);
            }

            if ($this->isZonesCollectionRequest($request, 'GET')) {
                return $this->zonesResponse(
                    $created
                        ? [$this->zonePayload($zoneId, $accountId, 'pending')]
                        : [],
                );
            }

            if ($this->isZonesCollectionRequest($request, 'POST')) {
                $created = true;

                return Http::response([
                    'success' => true,
                    'result' => $this->zonePayload(
                        $zoneId,
                        $accountId,
                        'pending',
                    ),
                ]);
            }

            return Http::response([], 500);
        });

        Livewire::actingAs($user)
            ->test(Zones::class)
            ->assertSet('canManageZones', true)
            ->call('openCreateZone')
            ->set('zoneAccountId', $this->accountId)
            ->set('zoneDomain', 'Example.COM.')
            ->call('createZone')
            ->assertHasNoErrors()
            ->assertSet('selectedZoneId', $this->zoneId)
            ->assertSet('zoneFormOpen', false)
            ->assertSee('example.com')
            ->assertSee('alice.ns.cloudflare.com')
            ->assertSee('bob.ns.cloudflare.com')
            ->assertSee('در انتظار فعال‌سازی');

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.cloudflare.com/client/v4/zones'
            && $request['account']['id'] === $accountId
            && $request['name'] === 'example.com'
            && $request['type'] === 'full');
    }

    public function test_refresh_moves_pending_zone_to_active_state(): void
    {
        $user = $this->userWithConnection();
        $accountId = $this->accountId;
        $zoneId = $this->zoneId;
        $zoneReads = 0;

        Http::fake(function (Request $request) use (
            $accountId,
            $zoneId,
            &$zoneReads,
        ) {
            if ($this->isAccountsRequest($request)) {
                return $this->accountsResponse($accountId);
            }

            if ($this->isZonesCollectionRequest($request, 'GET')) {
                $zoneReads++;
                $status = $zoneReads === 1 ? 'pending' : 'active';

                return $this->zonesResponse([
                    $this->zonePayload($zoneId, $accountId, $status),
                ]);
            }

            return Http::response([], 500);
        });

        Livewire::actingAs($user)
            ->test(Zones::class)
            ->assertSee('در انتظار فعال‌سازی')
            ->call('refreshData')
            ->assertSee('دامنه فعال است')
            ->assertSee('مدیریت DNS');
    }

    public function test_zone_write_scope_is_required_for_mutations_but_not_reads(): void
    {
        $user = $this->userWithConnection([
            'account-settings.read',
            'zone.read',
            'dns.read',
            'dns.write',
            'offline_access',
        ]);
        $accountId = $this->accountId;

        Http::fake(function (Request $request) use ($accountId) {
            if ($this->isAccountsRequest($request)) {
                return $this->accountsResponse($accountId);
            }

            if ($this->isZonesCollectionRequest($request, 'GET')) {
                return $this->zonesResponse([]);
            }

            return Http::response([], 500);
        });

        Livewire::actingAs($user)
            ->test(Zones::class)
            ->assertSet('needsReconnect', false)
            ->assertSet('canManageZones', false)
            ->assertSee('Zone Write هنوز فعال نیست')
            ->assertSee('zone.write')
            ->call('openCreateZone')
            ->assertSet('zoneFormOpen', false)
            ->assertSet(
                'error',
                'برای افزودن یا حذف دامنه باید مجوز zone.write به اتصال Cloudflare اضافه شود.',
            );

        Http::assertNotSent(static fn (Request $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/zones'));
    }

    public function test_zone_deletion_requires_explicit_confirmation_and_remote_resolution(): void
    {
        $user = $this->userWithConnection();
        $accountId = $this->accountId;
        $zoneId = $this->zoneId;
        $deleted = false;
        $zoneUrl = "https://api.cloudflare.com/client/v4/zones/{$zoneId}";
        $methods = [];

        Http::fake(function (Request $request) use (
            $accountId,
            $zoneId,
            $zoneUrl,
            &$deleted,
            &$methods,
        ) {
            if ($this->isAccountsRequest($request)) {
                return $this->accountsResponse($accountId);
            }

            if ($this->isZonesCollectionRequest($request, 'GET')) {
                return $this->zonesResponse(
                    $deleted
                        ? []
                        : [$this->zonePayload($zoneId, $accountId, 'pending')],
                );
            }

            if ($request->url() === $zoneUrl) {
                $methods[] = $request->method();

                if ($request->method() === 'GET') {
                    return Http::response([
                        'success' => true,
                        'result' => $this->zonePayload(
                            $zoneId,
                            $accountId,
                            'pending',
                        ),
                    ]);
                }

                if ($request->method() === 'DELETE') {
                    $deleted = true;

                    return Http::response([
                        'success' => true,
                        'result' => ['id' => $zoneId],
                    ]);
                }
            }

            return Http::response([], 500);
        });

        Livewire::actingAs($user)
            ->test(Zones::class)
            ->call('confirmDeleteZone', $this->zoneId)
            ->assertSet('pendingDeleteZoneId', $this->zoneId)
            ->call('deleteZone')
            ->assertSet('pendingDeleteZoneId', null)
            ->assertSet('selectedZoneId', null)
            ->assertSet('status', 'دامنه با موفقیت از Cloudflare حذف شد.');

        self::assertSame(['GET', 'DELETE'], $methods);
    }

    /**
     * @param list<string>|null $scopes
     */
    private function userWithConnection(?array $scopes = null): User
    {
        $user = User::factory()->create();

        IntegrationConnection::query()->create([
            'user_id' => $user->getKey(),
            'provider' => IntegrationProvider::Cloudflare,
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'scopes' => $scopes ?? $this->fullScopes(),
            'access_token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ]);

        return $user;
    }

    /** @return list<string> */
    private function fullScopes(): array
    {
        return [
            'account-settings.read',
            'zone.read',
            'zone.write',
            'dns.read',
            'dns.write',
            'offline_access',
        ];
    }

    private function isAccountsRequest(Request $request): bool
    {
        return $request->method() === 'GET'
            && str_starts_with(
                $request->url(),
                'https://api.cloudflare.com/client/v4/accounts?',
            );
    }

    private function isZonesCollectionRequest(
        Request $request,
        string $method,
    ): bool {
        if ($request->method() !== $method) {
            return false;
        }

        if ($method === 'POST') {
            return $request->url() === 'https://api.cloudflare.com/client/v4/zones';
        }

        return str_starts_with(
            $request->url(),
            'https://api.cloudflare.com/client/v4/zones?',
        );
    }

    private function accountsResponse(string $accountId)
    {
        return Http::response([
            'success' => true,
            'result' => [[
                'id' => $accountId,
                'name' => 'Primary Account',
            ]],
            'result_info' => ['total_pages' => 1],
        ]);
    }

    /** @param list<array<string, mixed>> $zones */
    private function zonesResponse(array $zones)
    {
        return Http::response([
            'success' => true,
            'result' => $zones,
            'result_info' => ['total_pages' => 1],
        ]);
    }

    /** @return array<string, mixed> */
    private function zonePayload(
        string $zoneId,
        string $accountId,
        string $status,
    ): array {
        return [
            'id' => $zoneId,
            'name' => 'example.com',
            'status' => $status,
            'paused' => false,
            'type' => 'full',
            'development_mode' => 0,
            'account' => [
                'id' => $accountId,
                'name' => 'Primary Account',
            ],
            'name_servers' => [
                'alice.ns.cloudflare.com',
                'bob.ns.cloudflare.com',
            ],
            'created_on' => '2026-08-18T06:00:00Z',
            'activated_on' => $status === 'active'
                ? '2026-08-18T06:05:00Z'
                : null,
        ];
    }
}
