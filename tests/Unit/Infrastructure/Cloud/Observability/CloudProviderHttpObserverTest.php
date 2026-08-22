<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Cloud\Observability;

use App\Infrastructure\Cloud\Observability\CloudProviderHttpObserver;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request as WebRequest;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class CloudProviderHttpObserverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'cloud.providers.arvan.base_url',
            'https://napi.arvancloud.ir/ecc/v1',
        );
        config()->set(
            'cloud.providers.liara.base_url',
            'https://iaas-api.liara.ir',
        );

        $request = WebRequest::create('/', 'GET');
        $request->headers->set(
            'X-Request-ID',
            'coreflare-test-request',
        );

        $this->app->instance(
            'request',
            $request,
        );
    }

    public function test_it_logs_failed_arvan_response_with_structured_context(): void
    {
        Log::spy();

        $observer = new CloudProviderHttpObserver;
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
                    new PsrResponse(
                        status: 503,
                        headers: [
                            'X-Request-ID' => 'arvan-request-123',
                        ],
                    ),
                ),
            ),
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Cloud provider HTTP request failed.',
                Mockery::on(
                    static fn (array $context): bool =>
                        $context['provider'] === 'arvan'
                        && $context['operation'] === 'http.get'
                        && $context['method'] === 'GET'
                        && $context['endpoint'] === 'regions'
                        && $context['status'] === 503
                        && $context['outcome'] === 'failure'
                        && $context['error_category'] === 'provider_server_error'
                        && $context['provider_request_id'] === 'arvan-request-123'
                        && $context['correlation_id'] === 'coreflare-test-request'
                        && is_float($context['duration_ms']),
                ),
            );
    }

    public function test_it_classifies_liara_authentication_failure_without_logging_secrets(): void
    {
        Log::spy();

        $observer = new CloudProviderHttpObserver;
        $request = $this->clientRequest(
            method: 'GET',
            url: 'https://iaas-api.liara.ir/v1/regions?token=must-not-be-logged',
            headers: [
                'Authorization' => 'Bearer super-secret-token',
            ],
        );

        $observer->requestSending(
            new RequestSending($request),
        );
        $observer->responseReceived(
            new ResponseReceived(
                request: $request,
                response: new ClientResponse(
                    new PsrResponse(status: 401),
                ),
            ),
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Cloud provider HTTP request failed.',
                Mockery::on(
                    static function (array $context): bool {
                        $serialized = json_encode($context);

                        return $context['provider'] === 'liara'
                            && $context['endpoint'] === 'v1/regions'
                            && $context['error_category'] === 'authentication'
                            && is_string($serialized)
                            && ! str_contains(
                                $serialized,
                                'super-secret-token',
                            )
                            && ! str_contains(
                                $serialized,
                                'must-not-be-logged',
                            );
                    },
                ),
            );
    }

    public function test_it_classifies_liara_precondition_failures_as_validation(): void
    {
        Log::spy();

        foreach ([412, 428] as $status) {
            $observer = new CloudProviderHttpObserver;
            $request = $this->clientRequest(
                method: 'POST',
                url: 'https://iaas-api.liara.ir/v1/servers',
            );

            $observer->requestSending(
                new RequestSending($request),
            );
            $observer->responseReceived(
                new ResponseReceived(
                    request: $request,
                    response: new ClientResponse(
                        new PsrResponse(status: $status),
                    ),
                ),
            );
        }

        Log::shouldHaveReceived('warning')
            ->twice()
            ->with(
                'Cloud provider HTTP request failed.',
                Mockery::on(
                    static fn (array $context): bool =>
                        $context['provider'] === 'liara'
                        && in_array($context['status'], [412, 428], true)
                        && $context['error_category'] === 'validation',
                ),
            );
    }

    public function test_it_classifies_provider_transport_timeout(): void
    {
        Log::spy();

        $observer = new CloudProviderHttpObserver;
        $request = $this->clientRequest(
            method: 'POST',
            url: 'https://napi.arvancloud.ir/ecc/v1/regions/ir-thr-ba1/sizes/eco-1/disk',
        );

        $observer->requestSending(
            new RequestSending($request),
        );
        $observer->connectionFailed(
            new ConnectionFailed(
                request: $request,
                exception: new ConnectionException(
                    'cURL error 28: Operation timed out',
                ),
            ),
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Cloud provider HTTP connection failed.',
                Mockery::on(
                    static fn (array $context): bool =>
                        $context['provider'] === 'arvan'
                        && $context['operation'] === 'http.post'
                        && $context['error_category'] === 'timeout'
                        && $context['status'] === null
                        && $context['outcome'] === 'failure'
                        && $context['correlation_id'] === 'coreflare-test-request',
                ),
            );
    }

    public function test_it_ignores_non_provider_http_requests(): void
    {
        Log::spy();

        $observer = new CloudProviderHttpObserver;
        $request = $this->clientRequest(
            method: 'GET',
            url: 'https://example.com/api/status',
        );

        $observer->requestSending(
            new RequestSending($request),
        );
        $observer->responseReceived(
            new ResponseReceived(
                request: $request,
                response: new ClientResponse(
                    new PsrResponse(status: 500),
                ),
            ),
        );

        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('debug');
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function clientRequest(
        string $method,
        string $url,
        array $headers = [],
    ): ClientRequest {
        return new ClientRequest(
            new PsrRequest(
                method: $method,
                uri: $url,
                headers: $headers,
            ),
        );
    }
}
