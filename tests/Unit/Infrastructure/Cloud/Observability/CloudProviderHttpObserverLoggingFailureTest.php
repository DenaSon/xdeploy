<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Observability;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Contracts\CloudProviderHealthStoreInterface;
use App\Domain\Cloud\DTOs\CloudProviderHealthSnapshot;
use App\Domain\Cloud\Enums\CloudProviderHealthStatus;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Infrastructure\Cloud\Observability\CloudProviderHttpObserver;
use Closure;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

final class CloudProviderHttpObserverLoggingFailureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cloud.providers.arvan.base_url',
            'https://napi.arvancloud.ir/ecc/v1',
        );
    }

    public function test_successful_provider_response_survives_broken_debug_log_sink(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Log stream is not writable.',
                ),
            );

        $store = $this->healthStore();
        $observer = new CloudProviderHttpObserver(
            new CloudProviderHealthEngine($store),
        );
        $request = $this->clientRequest(
            method: 'GET',
            url: 'https://napi.arvancloud.ir/ecc/v1/regions',
        );

        $observer->requestSending(
            new RequestSending($request),
        );
        $observer->responseReceived(
            new ResponseReceived(
                request: $request,
                response: new ClientResponse(
                    new PsrResponse(status: 200),
                ),
            ),
        );

        self::assertSame(
            CloudProviderHealthStatus::Healthy,
            $store->get(
                CloudProviderType::Arvan,
            )?->status,
        );
    }

    public function test_failed_provider_response_survives_broken_warning_log_sink(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->andThrow(
                new RuntimeException(
                    'Log stream is not writable.',
                ),
            );

        $store = $this->healthStore();
        $observer = new CloudProviderHttpObserver(
            new CloudProviderHealthEngine($store),
        );
        $request = $this->clientRequest(
            method: 'GET',
            url: 'https://napi.arvancloud.ir/ecc/v1/regions',
        );

        $observer->requestSending(
            new RequestSending($request),
        );
        $observer->responseReceived(
            new ResponseReceived(
                request: $request,
                response: new ClientResponse(
                    new PsrResponse(status: 503),
                ),
            ),
        );

        self::assertSame(
            CloudProviderHealthStatus::Degraded,
            $store->get(
                CloudProviderType::Arvan,
            )?->status,
        );
    }

    private function healthStore(): CloudProviderHealthStoreInterface
    {
        return new class implements CloudProviderHealthStoreInterface
        {
            /** @var array<string, CloudProviderHealthSnapshot> */
            private array $snapshots = [];

            public function get(
                CloudProviderType $provider,
            ): ?CloudProviderHealthSnapshot {
                return $this->snapshots[$provider->value]
                    ?? null;
            }

            public function update(
                CloudProviderType $provider,
                Closure $mutator,
            ): CloudProviderHealthSnapshot {
                $snapshot = $mutator(
                    $this->get($provider),
                );

                $this->snapshots[$provider->value] =
                    $snapshot;

                return $snapshot;
            }
        };
    }

    private function clientRequest(
        string $method,
        string $url,
    ): ClientRequest {
        return new ClientRequest(
            new PsrRequest(
                method: $method,
                uri: $url,
            ),
        );
    }
}
