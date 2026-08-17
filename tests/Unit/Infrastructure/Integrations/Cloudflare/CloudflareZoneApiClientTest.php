<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integrations\Cloudflare;

use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareZoneApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare_api.base_url' => 'https://api.cloudflare.com/client/v4',
            'services.cloudflare_api.connect_timeout' => 5,
            'services.cloudflare_api.timeout' => 15,
            'services.cloudflare_api.max_pages' => 20,
        ]);
    }

    public function test_zone_details_are_normalized_for_onboarding(): void
    {
        $zoneId = str_repeat('c', 32);

        Http::fake([
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}" => Http::response([
                'success' => true,
                'result' => [
                    'id' => $zoneId,
                    'name' => 'Example.COM',
                    'status' => 'pending',
                    'paused' => false,
                    'type' => 'full',
                    'development_mode' => 0,
                    'account' => [
                        'id' => str_repeat('a', 32),
                        'name' => 'Primary Account',
                    ],
                    'name_servers' => [
                        'ALICE.NS.CLOUDFLARE.COM',
                        'bob.ns.cloudflare.com',
                    ],
                    'original_name_servers' => [
                        'ns1.example.net',
                    ],
                    'created_on' => '2026-08-18T00:00:00Z',
                    'activated_on' => null,
                ],
            ]),
        ]);

        $zone = app(CloudflareApiClient::class)
            ->zone('access-token', $zoneId);

        self::assertSame($zoneId, $zone['id']);
        self::assertSame('example.com', $zone['name']);
        self::assertSame('pending', $zone['status']);
        self::assertSame(
            ['alice.ns.cloudflare.com', 'bob.ns.cloudflare.com'],
            $zone['name_servers'],
        );
        self::assertSame(
            ['ns1.example.net'],
            $zone['original_name_servers'],
        );
        self::assertNull($zone['activated_on']);
    }

    public function test_zone_create_uses_full_setup_and_selected_account(): void
    {
        $accountId = str_repeat('a', 32);
        $zoneId = str_repeat('c', 32);
        $url = 'https://api.cloudflare.com/client/v4/zones';

        Http::fake(static function (Request $request) use (
            $url,
            $zoneId,
            $accountId,
        ) {
            if ($request->method() !== 'POST' || $request->url() !== $url) {
                return Http::response([], 500);
            }

            return Http::response([
                'success' => true,
                'result' => [
                    'id' => $zoneId,
                    'name' => 'example.com',
                    'status' => 'pending',
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
                ],
            ]);
        });

        $zone = app(CloudflareApiClient::class)
            ->createZone(
                accessToken: 'access-token',
                accountId: $accountId,
                name: ' Example.COM. ',
            );

        self::assertSame('example.com', $zone['name']);
        self::assertSame('pending', $zone['status']);

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === $url
            && $request->hasHeader('Authorization', 'Bearer access-token')
            && $request['account']['id'] === $accountId
            && $request['name'] === 'example.com'
            && $request['type'] === 'full');
    }

    public function test_zone_delete_requires_matching_remote_identifier(): void
    {
        $zoneId = str_repeat('c', 32);
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}";

        Http::fake([
            $url => Http::response([
                'success' => true,
                'result' => ['id' => $zoneId],
            ]),
        ]);

        app(CloudflareApiClient::class)
            ->deleteZone('access-token', $zoneId);

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === $url);
    }

    public function test_invalid_create_account_identifier_is_rejected_before_network_call(): void
    {
        Http::fake();

        try {
            app(CloudflareApiClient::class)
                ->createZone(
                    accessToken: 'access-token',
                    accountId: 'not-an-account-id',
                    name: 'example.com',
                );

            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(
                CloudflareApiException::INVALID_REQUEST,
                $exception->reason,
            );
        }

        Http::assertNothingSent();
    }
}
