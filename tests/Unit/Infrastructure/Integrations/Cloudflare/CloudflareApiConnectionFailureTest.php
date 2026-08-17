<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integrations\Cloudflare;

use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudflareApiConnectionFailureTest extends TestCase
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

    public function test_read_request_retries_once_after_connection_failure(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('simulated timeout');
            }

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
        });

        $accounts = app(CloudflareApiClient::class)
            ->accounts('access-token');

        self::assertSame(2, $attempts);
        self::assertSame('Primary Account', $accounts[0]['name']);
    }

    public function test_exhausted_read_connection_failures_are_normalized(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('simulated timeout');
        });

        try {
            app(CloudflareApiClient::class)
                ->accounts('access-token');

            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(2, $attempts);
            self::assertSame(
                CloudflareApiException::CONNECTION_FAILED,
                $exception->reason,
            );
            self::assertSame(
                'Cloudflare API connection failed.',
                $exception->getMessage(),
            );
            self::assertInstanceOf(
                ConnectionException::class,
                $exception->getPrevious(),
            );
            self::assertStringNotContainsString(
                'access-token',
                $exception->getMessage(),
            );
        }
    }

    public function test_dns_mutation_connection_failure_is_not_retried(): void
    {
        $attempts = 0;
        $zoneId = str_repeat('c', 32);

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new ConnectionException('simulated timeout');
        });

        try {
            app(CloudflareApiClient::class)
                ->createDnsRecord(
                    'access-token',
                    $zoneId,
                    [
                        'type' => 'A',
                        'name' => 'app.example.com',
                        'content' => '203.0.113.10',
                        'ttl' => 1,
                        'proxied' => false,
                    ],
                );

            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(1, $attempts);
            self::assertSame(
                CloudflareApiException::CONNECTION_FAILED,
                $exception->reason,
            );
        }
    }
}
