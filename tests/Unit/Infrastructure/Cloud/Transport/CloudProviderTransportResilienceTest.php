<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Transport;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Infrastructure\Cloud\ArvanCloud\ArvanCloudClient;
use App\Infrastructure\Cloud\Liara\LiaraCloudClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CloudProviderTransportResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_arvan_catalog_get_retries_one_transient_server_failure(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([], 503)
                ->push(['data' => []], 200),
        ]);

        $result = $this->arvan()->getCatalog('regions');

        $this->assertSame(['data' => []], $result);
        Http::assertSentCount(2);
    }

    public function test_arvan_read_only_pricing_post_retries_one_transient_server_failure(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([], 503)
                ->push([
                    'hourly' => '100',
                    'monthly' => '72000',
                ], 200),
        ]);

        $result = $this->arvan()->postPricing(
            'regions/ir-thr-ba1/sizes/eco-1/disk',
            ['volume_size' => 30],
        );

        $this->assertSame('100', $result['hourly']);
        Http::assertSentCount(2);
    }

    public function test_arvan_mutation_post_is_never_retried(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        try {
            $this->arvan()->post(
                'regions/ir-thr-ba1/servers',
                ['name' => 'coreflare-test'],
            );

            $this->fail('Expected provider connection exception.');
        } catch (CloudConnectionException) {
            Http::assertSentCount(1);
        }
    }

    public function test_arvan_authentication_failure_is_not_retried(): void
    {
        Http::fake([
            '*' => Http::response([], 401),
        ]);

        try {
            $this->arvan()->getCatalog('regions');

            $this->fail('Expected provider authentication exception.');
        } catch (CloudAuthenticationException) {
            Http::assertSentCount(1);
        }
    }

    public function test_arvan_rate_limit_is_not_retried(): void
    {
        Http::fake([
            '*' => Http::response([], 429),
        ]);

        try {
            $this->arvan()->getCatalog('regions');

            $this->fail('Expected provider rate-limit exception.');
        } catch (CloudRateLimitException) {
            Http::assertSentCount(1);
        }
    }

    public function test_arvan_full_transport_timeout_is_not_retried(): void
    {
        Http::fake([
            '*' => Http::failedConnection(
                'cURL error 28: Operation timed out',
            ),
        ]);

        try {
            $this->arvan()->getCatalog('regions');

            $this->fail('Expected provider connection exception.');
        } catch (CloudConnectionException) {
            Http::assertSentCount(1);
        }
    }

    public function test_liara_catalog_get_retries_one_transient_server_failure(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([], 503)
                ->push(['plans' => []], 200),
        ]);

        $result = $this->liara()->getCatalog('plans');

        $this->assertSame(['plans' => []], $result);
        Http::assertSentCount(2);
    }

    public function test_liara_mutation_post_is_never_retried(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        try {
            $this->liara()->post(
                'vm',
                ['name' => 'coreflare-test'],
            );

            $this->fail('Expected provider connection exception.');
        } catch (CloudConnectionException) {
            Http::assertSentCount(1);
        }
    }

    private function arvan(): ArvanCloudClient
    {
        return new ArvanCloudClient(
            baseUrl: 'https://arvan.example.test/ecc/v1',
            apiKey: 'test-api-key',
            connectTimeout: 5,
            requestTimeout: 15,
            catalogConnectTimeout: 3,
            catalogRequestTimeout: 8,
            pricingConnectTimeout: 3,
            pricingRequestTimeout: 10,
            retryMaxAttempts: 2,
            retryDelayMilliseconds: 0,
        );
    }

    private function liara(): LiaraCloudClient
    {
        return new LiaraCloudClient(
            baseUrl: 'https://liara.example.test',
            apiToken: 'test-api-token',
            connectTimeout: 5,
            requestTimeout: 15,
            catalogConnectTimeout: 3,
            catalogRequestTimeout: 8,
            retryMaxAttempts: 2,
            retryDelayMilliseconds: 0,
        );
    }
}
