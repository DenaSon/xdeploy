<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integrations\Cloudflare;

use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class CloudflareApiErrorObservabilityTest extends TestCase
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

    public function test_remote_error_metadata_is_preserved_and_safely_logged(): void
    {
        $accountId = str_repeat('a', 32);
        $url = 'https://api.cloudflare.com/client/v4/zones';

        Log::spy();

        Http::fake([
            $url => Http::response([
                'success' => false,
                'errors' => [[
                    'code' => 1061,
                    'message' => "Zone already exists.\nUse the existing zone.",
                ]],
                'messages' => [],
                'result' => null,
            ], 400),
        ]);

        try {
            app(CloudflareApiClient::class)->createZone(
                accessToken: 'sensitive-access-token',
                accountId: $accountId,
                name: 'example.com',
            );

            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(
                CloudflareApiException::INVALID_REQUEST,
                $exception->reason,
            );
            self::assertSame(400, $exception->httpStatus);
            self::assertSame(1061, $exception->remoteCode);
            self::assertSame(
                'Zone already exists. Use the existing zone.',
                $exception->remoteMessage,
            );
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'cloudflare.api.request_failed',
                Mockery::on(static function (array $context): bool {
                    $serialized = json_encode($context);

                    return $context === [
                        'method' => 'POST',
                        'path' => '/zones',
                        'http_status' => 400,
                        'remote_code' => 1061,
                        'remote_message' => 'Zone already exists. Use the existing zone.',
                    ]
                        && is_string($serialized)
                        && ! str_contains($serialized, 'sensitive-access-token')
                        && ! array_key_exists('headers', $context)
                        && ! array_key_exists('body', $context)
                        && ! array_key_exists('payload', $context);
                }),
            );
    }

    public function test_success_false_response_is_observable_even_with_successful_http_status(): void
    {
        $zoneId = str_repeat('c', 32);
        $url = "https://api.cloudflare.com/client/v4/zones/{$zoneId}";

        Log::spy();

        Http::fake([
            $url => Http::response([
                'success' => false,
                'errors' => [[
                    'code' => 'remote-error',
                    'message' => 'Cloudflare could not process the request.',
                ]],
                'result' => null,
            ], 200),
        ]);

        try {
            app(CloudflareApiClient::class)->zone(
                'sensitive-access-token',
                $zoneId,
            );

            self::fail('Expected CloudflareApiException was not thrown.');
        } catch (CloudflareApiException $exception) {
            self::assertSame(
                CloudflareApiException::REMOTE_ERROR,
                $exception->reason,
            );
            self::assertSame(200, $exception->httpStatus);
            self::assertSame('remote-error', $exception->remoteCode);
            self::assertSame(
                'Cloudflare could not process the request.',
                $exception->remoteMessage,
            );
        }

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'cloudflare.api.request_failed',
                Mockery::on(static fn (array $context): bool => $context['method'] === 'GET'
                    && $context['path'] === "/zones/{$zoneId}"
                    && $context['http_status'] === 200
                    && $context['remote_code'] === 'remote-error'),
            );
    }
}
