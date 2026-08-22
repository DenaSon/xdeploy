<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\Observability\CloudProviderHttpObserver;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

final class CloudProviderHealthIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        config()->set(
            'cloud.providers.arvan.base_url',
            'https://napi.arvancloud.ir/ecc/v1',
        );

        Cache::flush();
    }

    public function test_provider_server_error_updates_health_snapshot(): void
    {
        $observer = $this->app->make(
            CloudProviderHttpObserver::class,
        );
        $request = $this->request(
            'https://napi.arvancloud.ir/ecc/v1/regions',
        );

        $observer->requestSending(new RequestSending($request));
        $observer->responseReceived(
            new ResponseReceived(
                request: $request,
                response: new ClientResponse(
                    new PsrResponse(status: 503),
                ),
            ),
        );

        $snapshot = $this->app->make(
            CloudProviderHealthEngine::class,
        )->snapshot(CloudProviderType::Arvan);

        $this->assertNotNull($snapshot);
        $this->assertSame(
            CloudProviderHealthStatus::Degraded,
            $snapshot->status,
        );
        $this->assertSame(1, $snapshot->consecutiveAvailabilityFailures);
        $this->assertSame(
            CloudProviderHealthFailureCategory::ProviderServerError,
            $snapshot->lastErrorCategory,
        );
        $this->assertSame(503, $snapshot->lastErrorHttpStatus);
        $this->assertSame('http.get', $snapshot->lastOperation);
    }

    public function test_authentication_failure_is_recorded_without_declaring_outage(): void
    {
        $observer = $this->app->make(
            CloudProviderHttpObserver::class,
        );
        $request = $this->request(
            'https://napi.arvancloud.ir/ecc/v1/regions',
        );

        $observer->requestSending(new RequestSending($request));
        $observer->responseReceived(
            new ResponseReceived(
                request: $request,
                response: new ClientResponse(
                    new PsrResponse(status: 401),
                ),
            ),
        );

        $snapshot = $this->app->make(
            CloudProviderHealthEngine::class,
        )->snapshot(CloudProviderType::Arvan);

        $this->assertNotNull($snapshot);
        $this->assertNull($snapshot->status);
        $this->assertSame(
            CloudProviderHealthFailureCategory::Authentication,
            $snapshot->lastErrorCategory,
        );
        $this->assertSame(0, $snapshot->consecutiveAvailabilityFailures);
    }

    private function request(string $url): ClientRequest
    {
        return new ClientRequest(
            new PsrRequest(
                method: 'GET',
                uri: $url,
            ),
        );
    }
}
