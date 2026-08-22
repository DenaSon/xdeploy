<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Observability;

use App\Application\Cloud\Services\CloudProviderHealthEngine;
use App\Domain\Cloud\Enums\CloudProviderHealthFailureCategory;
use App\Domain\Cloud\Enums\CloudProviderType;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request as WebRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class CloudProviderHttpObserver
{
    private const string CORRELATION_ATTRIBUTE =
        'coreflare_cloud_correlation_id';

    /** @var array<int, float> */
    private array $startedAt = [];

    private ?string $processCorrelationId = null;

    public function __construct(
        private readonly CloudProviderHealthEngine $health,
    ) {}

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

        $operation = $this->operation($event->request);
        $durationMs = $this->durationMilliseconds(
            $event->request,
        );

        $context = [
            ...$providerContext,
            'operation' => $operation,
            'method' => strtoupper($event->request->method()),
            'status' => $event->response->status(),
            'duration_ms' => $durationMs,
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

            $this->debugSafely(
                'Cloud provider HTTP request completed.',
                $context,
            );

            $this->recordHealthSuccess(
                providerContext: $providerContext,
                latencyMs: $durationMs,
                operation: $operation,
            );

            return;
        }

        $category = $this->httpErrorCategory(
            $event->response->status(),
        );

        $context['outcome'] = 'failure';
        $context['error_category'] = $category->value;

        $this->warningSafely(
            'Cloud provider HTTP request failed.',
            $context,
        );

        $this->recordHealthFailure(
            providerContext: $providerContext,
            category: $category,
            httpStatus: $event->response->status(),
            latencyMs: $durationMs,
            operation: $operation,
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

        $operation = $this->operation($event->request);
        $durationMs = $this->durationMilliseconds(
            $event->request,
        );
        $category = $this->connectionErrorCategory(
            $event->exception->getMessage(),
        );

        $this->warningSafely(
            'Cloud provider HTTP connection failed.',
            [
                ...$providerContext,
                'operation' => $operation,
                'method' => strtoupper($event->request->method()),
                'status' => null,
                'duration_ms' => $durationMs,
                'correlation_id' => $this->correlationId(),
                'outcome' => 'failure',
                'error_category' => $category->value,
                'exception_class' => $event->exception::class,
            ],
        );

        $this->recordHealthFailure(
            providerContext: $providerContext,
            category: $category,
            httpStatus: null,
            latencyMs: $durationMs,
            operation: $operation,
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

    private function httpErrorCategory(
        int $status,
    ): CloudProviderHealthFailureCategory {
        return match (true) {
            in_array(
                $status,
                [400, 409, 412, 419, 422, 428],
                true,
            ) => CloudProviderHealthFailureCategory::Validation,
            $status === 401 => CloudProviderHealthFailureCategory::Authentication,
            $status === 402 => CloudProviderHealthFailureCategory::InsufficientBalance,
            $status === 403 => CloudProviderHealthFailureCategory::Authorization,
            $status === 404 => CloudProviderHealthFailureCategory::NotFound,
            $status === 408 => CloudProviderHealthFailureCategory::Timeout,
            $status === 429 => CloudProviderHealthFailureCategory::RateLimit,
            $status >= 500 => CloudProviderHealthFailureCategory::ProviderServerError,
            default => CloudProviderHealthFailureCategory::UnexpectedStatus,
        };
    }

    private function connectionErrorCategory(
        string $message,
    ): CloudProviderHealthFailureCategory {
        $message = strtolower($message);

        if (
            str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
        ) {
            return CloudProviderHealthFailureCategory::Timeout;
        }

        return CloudProviderHealthFailureCategory::Connection;
    }

    /**
     * @param  array{provider: string, endpoint: string}  $providerContext
     */
    private function recordHealthSuccess(
        array $providerContext,
        ?float $latencyMs,
        string $operation,
    ): void {
        $provider = CloudProviderType::tryFrom(
            $providerContext['provider'],
        );

        if (! $provider instanceof CloudProviderType) {
            return;
        }

        try {
            $this->health->recordSuccess(
                provider: $provider,
                latencyMs: $latencyMs,
                operation: $operation,
            );
        } catch (Throwable $exception) {
            $this->logHealthRecordingFailure(
                provider: $provider,
                exception: $exception,
            );
        }
    }

    /**
     * @param  array{provider: string, endpoint: string}  $providerContext
     */
    private function recordHealthFailure(
        array $providerContext,
        CloudProviderHealthFailureCategory $category,
        ?int $httpStatus,
        ?float $latencyMs,
        string $operation,
    ): void {
        $provider = CloudProviderType::tryFrom(
            $providerContext['provider'],
        );

        if (! $provider instanceof CloudProviderType) {
            return;
        }

        try {
            $this->health->recordFailure(
                provider: $provider,
                category: $category,
                httpStatus: $httpStatus,
                latencyMs: $latencyMs,
                operation: $operation,
            );
        } catch (Throwable $exception) {
            $this->logHealthRecordingFailure(
                provider: $provider,
                exception: $exception,
            );
        }
    }

    private function logHealthRecordingFailure(
        CloudProviderType $provider,
        Throwable $exception,
    ): void {
        $this->warningSafely(
            'Cloud provider health state update failed.',
            [
                'provider' => $provider->value,
                'exception_class' => $exception::class,
            ],
        );
    }

    /**
     * Logging is observability only. A broken file/stream handler must never
     * turn a successful provider operation into a failed business request.
     *
     * @param  array<string, mixed>  $context
     */
    private function debugSafely(
        string $message,
        array $context,
    ): void {
        try {
            Log::debug(
                $message,
                $context,
            );
        } catch (Throwable) {
            // Deliberately fail open: the provider operation remains authoritative.
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warningSafely(
        string $message,
        array $context,
    ): void {
        try {
            Log::warning(
                $message,
                $context,
            );
        } catch (Throwable) {
            // Deliberately fail open: logging must not become an outage source.
        }
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
