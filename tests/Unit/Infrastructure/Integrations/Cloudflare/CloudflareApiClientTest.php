<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integrations\Cloudflare;

use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareApiClientTest extends TestCase
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

    public function test_accounts_are_paginated_and_normalized(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts*' => Http::sequence()
                ->push([
                    'success' => true,
                    'result' => [
                        [
                            'id' => str_repeat('a', 32),
                            'name' => 'Primary Account',
                            'settings' => ['ignored' => true],
                        ],
                    ],
                    'result_info' => [
                        'page' => 1,
                        'per_page' => 50,
                        'total_count' => 51,
                    ],
                ])
                ->push([
                    'success' => true,
                    'result' => [
                        [
                            'id' => str_repeat('b', 32),
                            'name' => 'Secondary Account',
                        ],
                    ],
                    'result_info' => [
                        'page' => 2,
                        'per_page' => 50,
                        'total_count' => 51,
                    ],
                ]),
        ]);

        $accounts = app(CloudflareApiClient::class)
            ->accounts('access-token');

        self::assertSame(
            [
                [
                    'id' => str_repeat('a', 32),
                    'name' => 'Primary Account',
                ],
                [
                    'id' => str_repeat('b', 32),
                    'name' => 'Secondary Account',
                ],
            ],
            $accounts,
        );

        Http::assertSentCount(2);
        Http::assertSent(
            static fn ($request): bool => $request->hasHeader(
                'Authorization',
                'Bearer access-token',
            ),
        );
    }

    public function test_zones_and_dns_records_are_normalized(): void
    {
        $zoneId = str_repeat('c', 32);

        Http::fake([
            'https://api.cloudflare.com/client/v4/zones?*' => Http::response([
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
                            'id' => str_repeat('d', 32),
                            'name' => 'Primary Account',
                        ],
                        'name_servers' => [
                            'a.ns.cloudflare.com',
                            'b.ns.cloudflare.com',
                        ],
                    ],
                ],
                'result_info' => ['total_pages' => 1],
            ]),
            "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records?*" => Http::response([
                'success' => true,
                'result' => [
                    [
                        'id' => str_repeat('e', 32),
                        'type' => 'A',
                        'name' => 'app.example.com',
                        'content' => '203.0.113.10',
                        'proxiable' => true,
                        'proxied' => true,
                        'ttl' => 1,
                        'comment' => 'managed externally',
                        'modified_on' => '2026-08-18T00:00:00Z',
                    ],
                ],
                'result_info' => ['total_pages' => 1],
            ]),
        ]);

        $client = app(CloudflareApiClient::class);
        $zones = $client->zones('access-token');
        $records = $client->dnsRecords(
            'access-token',
            $zoneId,
        );

        self::assertSame('example.com', $zones[0]['name']);
        self::assertSame('Primary Account', $zones[0]['account']['name']);
        self::assertSame(
            ['a.ns.cloudflare.com', 'b.ns.cloudflare.com'],
            $zones[0]['name_servers'],
        );

        self::assertSame('A', $records[0]['type']);
        self::assertSame('app.example.com', $records[0]['name']);
        self::assertSame('203.0.113.10', $records[0]['content']);
        self::assertTrue($records[0]['proxiable']);
        self::assertTrue($records[0]['proxied']);
        self::assertSame(1, $records[0]['ttl']);
    }

    public function test_dns_record_mutations_use_expected_methods_and_payloads(): void
    {
        $zoneId = str_repeat('c', 32);
        $recordId = str_repeat('d', 32);
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/dns_records";
        $recordUrl = "{$url}/{$recordId}";

        Http::fake(static function (Request $request) use (
            $url,
            $recordUrl,
            $recordId,
        ) {
            if ($request->method() === 'POST' && $request->url() === $url) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => $recordId,
                        'type' => 'A',
                        'name' => 'api.example.com',
                        'content' => '203.0.113.20',
                        'proxiable' => true,
                        'proxied' => true,
                        'ttl' => 300,
                        'comment' => 'managed by Coreflare',
                        'modified_on' => '2026-08-18T00:00:00Z',
                    ],
                ]);
            }

            if ($request->method() === 'PATCH' && $request->url() === $recordUrl) {
                return Http::response([
                    'success' => true,
                    'result' => [
                        'id' => $recordId,
                        'type' => 'A',
                        'name' => 'api.example.com',
                        'content' => '203.0.113.21',
                        'proxiable' => true,
                        'proxied' => false,
                        'ttl' => 900,
                        'comment' => 'updated by Coreflare',
                        'modified_on' => '2026-08-18T00:01:00Z',
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

        $client = app(CloudflareApiClient::class);

        $created = $client->createDnsRecord(
            'access-token',
            $zoneId,
            [
                'type' => 'A',
                'name' => 'api.example.com',
                'content' => '203.0.113.20',
                'ttl' => 300,
                'proxied' => true,
                'comment' => 'managed by Coreflare',
            ],
        );

        $updated = $client->updateDnsRecord(
            'access-token',
            $zoneId,
            $recordId,
            [
                'type' => 'A',
                'name' => 'api.example.com',
                'content' => '203.0.113.21',
                'ttl' => 900,
                'proxied' => false,
                'comment' => 'updated by Coreflare',
            ],
        );

        $client->deleteDnsRecord(
            'access-token',
            $zoneId,
            $recordId,
        );

        self::assertSame('203.0.113.20', $created['content']);
        self::assertTrue($created['proxied']);
        self::assertSame('203.0.113.21', $updated['content']);
        self::assertFalse($updated['proxied']);

        Http::assertSent(static function (Request $request) use ($url): bool {
            return $request->method() === 'POST'
                && $request->url() === $url
                && $request->hasHeader('Authorization', 'Bearer access-token')
                && $request['type'] === 'A'
                && $request['name'] === 'api.example.com'
                && $request['content'] === '203.0.113.20'
                && $request['ttl'] === 300
                && $request['proxied'] === true;
        });

        Http::assertSent(static function (Request $request) use ($recordUrl): bool {
            return $request->method() === 'PATCH'
                && $request->url() === $recordUrl
                && $request['content'] === '203.0.113.21'
                && $request['ttl'] === 900
                && $request['proxied'] === false;
        });

        Http::assertSent(static fn (Request $request): bool => $request->method() === 'DELETE'
            && $request->url() === $recordUrl);
    }

    public function test_api_failure_never_returns_untrusted_result(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts*' => Http::response(
                [
                    'success' => false,
                    'errors' => [
                        ['code' => 10000, 'message' => 'Authentication error'],
                    ],
                ],
                200,
            ),
        ]);

        try {
            app(CloudflareApiClient::class)
                ->accounts('access-token');
            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(
                CloudflareApiException::REMOTE_ERROR,
                $exception->reason,
            );
        }
    }

    public function test_unauthorized_response_is_classified_without_exposing_token(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/accounts*' => Http::response(
                [],
                401,
            ),
        ]);

        try {
            app(CloudflareApiClient::class)
                ->accounts('super-secret-token');
            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(
                CloudflareApiException::UNAUTHORIZED,
                $exception->reason,
            );
            self::assertStringNotContainsString(
                'super-secret-token',
                $exception->getMessage(),
            );
        }
    }
}
