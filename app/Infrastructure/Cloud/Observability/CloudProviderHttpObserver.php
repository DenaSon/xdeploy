<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Observability;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request as WebRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class CloudProviderHttpObserver
{
    private const string CORRELATION_ATTRIBUTE =
        'coreflare_cloud_correlation_id';

    /** @var array<int, float> */
    private array $startedAt = [];

    private ?string $processCorrelationId = null;

    public function requestSending(RequestSending $event): void
    {
        if ($this->providerContext($event->request) === null) {
            return;
        }

        $this->startedAt[spl_object_id($event->request)] =
            microtime(true);
    }

    public function responseReceived(ResponseReceived $event): void
    {
        $providerContext = $this->providerContext(
            $event->request,
        );

        if ($providerContext === null) {
            return;
        }

        $context = [
            ...$providerContext,
            'operation' => $this->operation($event->request),
            'method' => strtoupper($event->request->method()),
            'status' => $event->response->status(),
            'duration_ms' => $this->durationMilliseconds(
                $event->request,
            ),
            'correlation_id' => $this->correlationId(),
        ];

        $providerRequestId = $this->providerRequestId(
            $event->response->headers(),
        );

        if ($providerRequestId !== null) {
            $context['provider_request_id'] = $providerRequestId;
        }

        $rateLimitRemaining = trim(
            (string) $event->response->header(
                'X-RateLimit-Remaining',
            ),
        );

        if ($rateLimitRemaining !== '') {
            $context['rate_limit_remaining'] =
                $rateLimitRemaining;
        }

        if ($event->response->successful()) {
            $context['outcome'] = 'success';

            Log::debug(
                'Cloud provider HTTP request completed.',
                $context,
            );

            return;
        }

        $context['outcome'] = 'failure';
        $context['error_category'] = $this->httpErrorCategory(
            $event->response->status(),
        );

        Log::warning(
            'Cloud provider HTTP request failed.',
            $context,
        );
    }

    public function connectionFailed(ConnectionFailed $event): void
    {
        $providerContext = $this->providerContext(
            $event->request,
        );

        if ($providerContext === null) {
            return;
        }

        Log::warning(
            'Cloud provider HTTP connection failed.',
            [
                ...$providerContext,
                'operation' => $this->operation($event->request),
                'method' => strtoupper($event->request->method()),
                'status' => null,
                'duration_ms' => $this->durationMilliseconds(
                    $event->request,
                ),
                'correlation_id' => $this->correlationId(),
                'outcome' => 'failure',
                'error_category' => $this->connectionErrorCategory(
                    $event->exception->getMessage(),
                ),
                'exception_class' => $event->exception::class,
            ],
        );
    }

    /**
     * @return array{
     *     provider: string,
     *     endpoint: string
     * }|null
     */
    private function providerContext(
        ClientRequest $request,
    ): ?array {
        $requestUrl = parse_url(
            $request->url(),
        );

        if (! is_array($requestUrl)) {
            return null;
        }

        $requestHost = strtolower(
            trim((string) ($requestUrl['host'] ?? '')),
        );

        if ($requestHost === '') {
            return null;
        }

        $requestPort = $requestUrl['port'] ?? null;
        $requestPath = '/'.ltrim(
            (string) ($requestUrl['path'] ?? ''),
            '/',
        );

        $providers = config(
            'cloud.providers',
            [],
        );

        if (! is_array($providers)) {
            return null;
        }

        foreach ($providers as $provider => $configuration) {
            if (
                ! is_string($provider)
                || ! is_array($configuration)
            ) {
                continue;
            }

            $baseUrl = $configuration['base_url'] ?? null;

            if (! is_string($baseUrl) || trim($baseUrl) === '') {
                continue;
            }

            $base = parse_url($baseUrl);

            if (! is_array($base)) {
                continue;
            }

            $baseHost = strtolower(
                trim((string) ($base['host'] ?? '')),
            );

            if (
                $baseHost === ''
                || $baseHost !== $requestHost
                || ($base['port'] ?? null) !== $requestPort
            ) {
                continue;
            }

            $basePath = rtrim(
                '/'.ltrim(
                    (string) ($base['path'] ?? ''),
                    '/',
                ),
                '/',
            );

            if (
                $basePath !== ''
                && $basePath !== '/'
                && $requestPath !== $basePath
                && ! str_starts_with(
                    $requestPath,
                    $basePath.'/',
                )
            ) {
                continue;
            }

            $endpoint = $basePath === '' || $basePath === '/'
                ? ltrim($requestPath, '/')
                : ltrim(
                    substr(
                        $requestPath,
                        strlen($basePath),
                    ),
                    '/',
                );

            return [
                'provider' => strtolower(trim($provider)),
                'endpoint' => $endpoint !== ''
                    ? $endpoint
                    : '/',
            ];
        }

        return null;
    }

    private function operation(ClientRequest $request): string
    {
        return sprintf(
            'http.%s',
            strtolower($request->method()),
        );
    }

    private function durationMilliseconds(
        ClientRequest $request,
    ): ?float {
        $key = spl_object_id($request);
        $startedAt = $this->startedAt[$key] ?? null;

        unset($this->startedAt[$key]);

        if (! is_float($startedAt)) {
            return null;
        }

        return round(
            (microtime(true) - $startedAt) * 1_000,
            2,
        );
    }

    /**
     * @param  array<string, list<string>>  $headers
     */
    private function providerRequestId(array $headers): ?string
    {
        foreach (
            [
                'X-Request-ID',
                'X-Correlation-ID',
                'Request-ID',
            ] as $header
        ) {
            foreach ($headers as $name => $values) {
                if (strcasecmp($name, $header) !== 0) {
                    continue;
                }

                $value = trim(
                    (string) ($values[0] ?? ''),
                );

                if ($value !== '') {
                    return substr($value, 0, 200);
                }
            }
        }

        return null;
    }

    private function httpErrorCategory(int $status): string
    {
        return match (true) {
            in_array(
                $status,
                [400, 409, 412, 419, 422, 428],
                true,
            ) => 'validation',
            $status === 401 => 'authentication',
            $status === 402 => 'insufficient_balance',
            $status === 403 => 'authorization',
            $status === 404 => 'not_found',
            $status === 408 => 'timeout',
            $status === 429 => 'rate_limit',
            $status >= 500 => 'provider_server_error',
            default => 'unexpected_status',
        };
    }

    private function connectionErrorCategory(
        string $message,
    ): string {
        $message = strtolower($message);

        if (
            str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
        ) {
            return 'timeout';
        }

        return 'connection';
    }

    private function correlationId(): string
    {
        if (app()->bound('request')) {
            $request = app('request');

            if ($request instanceof WebRequest) {
                $existing = $request->attributes->get(
                    self::CORRELATION_ATTRIBUTE,
                );

                if (is_string($existing) && $existing !== '') {
                    return $existing;
                }

                foreach (
                    [
                        'X-Request-ID',
                        'X-Correlation-ID',
                    ] as $header
                ) {
                    $candidate = $this->normalizedCorrelationId(
                        $request->header($header),
                    );

                    if ($candidate !== null) {
                        $request->attributes->set(
                            self::CORRELATION_ATTRIBUTE,
                            $candidate,
                        );

                        return $candidate;
                    }
                }

                $generated = (string) Str::uuid();

                $request->attributes->set(
                    self::CORRELATION_ATTRIBUTE,
                    $generated,
                );

                return $generated;
            }
        }

        return $this->processCorrelationId
            ??= (string) Str::uuid();
    }

    private function normalizedCorrelationId(
        mixed $value,
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (
            $value === ''
            || strlen($value) > 100
            || preg_match(
                '/^[A-Za-z0-9._:-]+$/',
                $value,
            ) !== 1
        ) {
            return null;
        }

        return $value;
    }
}
