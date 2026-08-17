<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Integration\Enums\IntegrationProvider;
use App\Livewire\Integrations\Cloudflare\Overview;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

final class CloudflareDnsManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $zoneId;

    private string $recordId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zoneId = str_repeat('c', 32);
        $this->recordId = str_repeat('d', 32);

        config([
            'services.cloudflare_oauth.client_id' => 'cloudflare-client-id',
            'services.cloudflare_oauth.client_secret' => 'cloudflare-client-secret',
            'services.cloudflare_oauth.authorization_endpoint' => 'https://dash.cloudflare.com/oauth2/auth',
            'services.cloudflare_oauth.token_endpoint' => 'https://dash.cloudflare.com/oauth2/token',
            'services.cloudflare_oauth.revoke_endpoint' => 'https://dash.cloudflare.com/oauth2/revoke',
            'services.cloudflare_oauth.scopes' => $this->fullScopes(),
            'services.cloudflare_oauth.connect_timeout' => 5,
            'services.cloudflare_oauth.timeout' => 10,
            'services.cloudflare_api.base_url' => 'https://api.cloudflare.com/client/v4',
            'services.cloudflare_api.connect_timeout' => 5,
            'services.cloudflare_api.timeout' => 15,
            'services.cloudflare_api.max_pages' => 20,
        ]);
    }

    public function test_dns_record_can_be_created_from_selected_zone(): void
    {
        $user = $this->userWithConnection();
        $this->fakeCloudflareApi();

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->assertSet('canManageDns', true)
            ->assertSet('selectedZoneId', $this->zoneId)
            ->call('openCreateDnsRecord')
            ->set('dnsType', 'A')
            ->set('dnsName', 'api')
            ->set('dnsContent', '203.0.113.20')
            ->set('dnsTtl', '300')
            ->set('dnsProxied', true)
            ->set('dnsComment', 'managed by Coreflare')
            ->call('saveDnsRecord')
            ->assertHasNoErrors()
            ->assertSet('dnsFormOpen', false)
            ->assertSet('dnsStatus', 'رکورد DNS با موفقیت ساخته شد.');

        $url = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records";

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === $url
            && $request->hasHeader('Authorization', 'Bearer access-token')
            && $request['type'] === 'A'
            && $request['name'] === 'api.example.com'
            && $request['content'] === '203.0.113.20'
            && $request['ttl'] === 300
            && $request['proxied'] === true
            && $request['comment'] === 'managed by Coreflare');
    }

    public function test_dns_record_can_be_updated_without_trusting_client_record_data(): void
    {
        $user = $this->userWithConnection();
        $this->fakeCloudflareApi();

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->call('editDnsRecord', $this->recordId)
            ->assertSet('editingDnsRecordId', $this->recordId)
            ->assertSet('dnsName', 'app')
            ->set('dnsContent', '203.0.113.21')
            ->set('dnsTtl', '900')
            ->set('dnsProxied', false)
            ->set('dnsComment', 'updated by Coreflare')
            ->call('saveDnsRecord')
            ->assertHasNoErrors()
            ->assertSet('dnsStatus', 'رکورد DNS با موفقیت به‌روزرسانی شد.');

        $url = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records/{$this->recordId}";

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'PATCH'
            && $request->url() === $url
            && $request['type'] === 'A'
            && $request['name'] === 'app.example.com'
            && $request['content'] === '203.0.113.21'
            && $request['ttl'] === 900
            && $request['proxied'] === false);
    }

    public function test_dns_record_can_be_deleted_only_when_it_exists_in_loaded_zone_data(): void
    {
        $user = $this->userWithConnection();
        $this->fakeCloudflareApi();

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->call('deleteDnsRecord', $this->recordId)
            ->assertSet('dnsStatus', 'رکورد DNS با موفقیت حذف شد.')
            ->call('deleteDnsRecord', str_repeat('e', 32))
            ->assertSet('error', 'رکورد DNS انتخاب‌شده دیگر در داده‌های Cloudflare وجود ندارد.');

        $url = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/dns_records/{$this->recordId}";

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === $url);

        Http::assertNotSent(static fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), str_repeat('e', 32)));
    }

    public function test_dns_mutations_are_blocked_without_dns_write_scope_while_reads_still_work(): void
    {
        $user = $this->userWithConnection([
            'account-settings.read',
            'zone.read',
            'dns.read',
            'offline_access',
        ]);
        $this->fakeCloudflareApi();

        Livewire::actingAs($user)
            ->test(Overview::class)
            ->assertSet('canManageDns', false)
            ->assertSet('needsReconnect', false)
            ->assertSee('app.example.com')
            ->assertSee('dns.write')
            ->call('openCreateDnsRecord')
            ->assertSet('dnsFormOpen', false)
            ->assertSet('error', 'برای مدیریت DNS باید مجوز dns.write به اتصال Cloudflare اضافه شود.');

        Http::assertNotSent(static fn (Request $request): bool => in_array(
            $request->method(),
            ['POST', 'PATCH', 'DELETE'],
            true,
        ) && str_contains($request->url(), '/dns_records'));
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

    private function fakeCloudflareApi(): void
    {
        $zoneId = $this->zoneId;
        $recordId = $this->recordId;
        $recordsUrl = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records";
        $recordUrl = "{$recordsUrl}/{$recordId}";

        Http::fake(function (Request $request) use (
            $zoneId,
            $recordId,
            $recordsUrl,
            $recordUrl,
        ) {
            if (
                $request->method() === 'GET'
                && str_starts_with(
                    $request->url(),
                    'https://api.cloudflare.com/client/v4/accounts',
                )
            ) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        [
                            'id' => str_repeat('a', 32),
                            'name' => 'Primary Account',
                        ],
                    ],
                    'result_info' => ['total_pages' => 1],
                ]);
            }

            if (
                $request->method() === 'GET'
                && str_starts_with(
                    $request->url(),
                    'https://api.cloudflare.com/client/v4/zones?',
                )
            ) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        [
                            'id' => $zoneId,
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
                ]);
            }

            if (
                $request->method() === 'GET'
                && str_starts_with($request->url(), $recordsUrl.'?')
            ) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        $this->recordResponse(
                            content: '203.0.113.10',
                            proxied: true,
                            ttl: 1,
                        ),
                    ],
                    'result_info' => ['total_pages' => 1],
                ]);
            }

            if ($request->method() === 'POST' && $request->url() === $recordsUrl) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        ...$this->recordResponse(
                            content: '203.0.113.20',
                            proxied: true,
                            ttl: 300,
                        ),
                        'name' => 'api.example.com',
                        'comment' => 'managed by Coreflare',
                    ],
                ]);
            }

            if ($request->method() === 'PATCH' && $request->url() === $recordUrl) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        ...$this->recordResponse(
                            content: '203.0.113.21',
                            proxied: false,
                            ttl: 900,
                        ),
                        'comment' => 'updated by Coreflare',
                    ],
                ]);
            }

            if ($request->method() === 'DELETE' && $request->url() === $recordUrl) {
                return Http::response([
                    'result' => ['id' => $recordId],
                ]);
            }

            return Http::response([], 500);
        });
    }

    /** @return array<string, mixed> */
    private function recordResponse(
        string $content,
        bool $proxied,
        int $ttl,
    ): array {
        return [
            'id' => $this->recordId,
            'type' => 'A',
            'name' => 'app.example.com',
            'content' => $content,
            'proxiable' => true,
            'proxied' => $proxied,
            'ttl' => $ttl,
            'comment' => null,
            'modified_on' => '2026-08-18T00:00:00Z',
        ];
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
