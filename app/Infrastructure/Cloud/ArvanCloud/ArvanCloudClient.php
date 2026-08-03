<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

final class ArvanCloudClient
{
    private readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly int $connectTimeout;

    private readonly int $requestTimeout;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $connectTimeout,
        int $requestTimeout,
    ) {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
        $this->apiKey = $this->normalizeApiKey($apiKey);
        $this->connectTimeout = $this->validateTimeout(
            $connectTimeout,
            'connect timeout',
        );
        $this->requestTimeout = $this->validateTimeout(
            $requestTimeout,
            'request timeout',
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $endpoint = $this->normalizePath($path);
        $url = "{$this->baseUrl}/{$endpoint}";
        $startedAt = microtime(true);

        try {
            $response = Http::acceptJson()
                ->withHeaders([
                    'Authorization' => "Apikey {$this->apiKey}",
                ])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->requestTimeout)
                ->withoutRedirecting()
                ->get($url, $query);
        } catch (ConnectionException $exception) {
            $this->logConnectionFailure(
                endpoint: $endpoint,
                startedAt: $startedAt,
            );

            throw new CloudConnectionException(
                message: 'Could not connect to the cloud provider.',
                previous: $exception,
            );
        }

        $this->logResponse(
            endpoint: $endpoint,
            response: $response,
            startedAt: $startedAt,
        );

        if (! $response->successful()) {
            $this->throwForStatus($response);
        }

        return $this->decodeResponse($response);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($baseUrl === '') {
            throw new CloudConfigurationException(
                'ArvanCloud base URL is not configured.',
            );
        }

        $parts = parse_url($baseUrl);

        if (
            $parts === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new CloudConfigurationException(
                'ArvanCloud base URL must be a valid HTTPS URL.',
            );
        }

        return $baseUrl;
    }

    private function normalizeApiKey(string $apiKey): string
    {
        $apiKey = trim($apiKey);

        if ($apiKey === '') {
            throw new CloudConfigurationException(
                'ArvanCloud API key is not configured.',
            );
        }

        return $apiKey;
    }

    private function validateTimeout(int $timeout, string $name): int
    {
        if ($timeout < 1) {
            throw new CloudConfigurationException(
                sprintf(
                    'ArvanCloud %s must be greater than zero.',
                    $name,
                ),
            );
        }

        return $timeout;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new CloudValidationException(
                'Cloud provider request path cannot be empty.',
            );
        }

        if (
            str_contains($path, '\\')
            || str_contains($path, '?')
            || str_contains($path, '#')
            || preg_match('/[\x00-\x20\x7F]/', $path) === 1
        ) {
            throw new CloudValidationException(
                'Cloud provider request path is invalid.',
            );
        }

        $path = ltrim($path, '/');
        $decodedPath = rawurldecode($path);

        foreach (explode('/', $decodedPath) as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
            ) {
                throw new CloudValidationException(
                    'Cloud provider request path is invalid.',
                );
            }
        }

        return $path;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeResponse(Response $response): array
    {
        try {
            $payload = $response->json(
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new CloudUnexpectedResponseException(
                message: 'Cloud provider returned invalid JSON.',
                code: $response->status(),
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw new CloudUnexpectedResponseException(
                message: 'Cloud provider returned an unexpected JSON payload.',
                code: $response->status(),
            );
        }

        return $payload;
    }

    private function throwForStatus(Response $response): never
    {
        $status = $response->status();

        if (in_array($status, [400, 409, 422], true)) {
            throw new CloudValidationException(
                message: 'Cloud provider rejected the request.',
                code: $status,
            );
        }

        if ($status === 401) {
            throw new CloudAuthenticationException(
                message: 'Cloud provider authentication failed.',
                code: $status,
            );
        }

        if ($status === 403) {
            throw new CloudAuthorizationException(
                message: 'Cloud provider authorization failed.',
                code: $status,
            );
        }

        if ($status === 404) {
            throw new CloudResourceNotFoundException(
                message: 'Cloud provider resource was not found.',
                code: $status,
            );
        }

        if ($status === 429) {
            throw new CloudRateLimitException(
                message: 'Cloud provider rate limit exceeded.',
                retryAfterSeconds: $this->retryAfterSeconds($response),
                code: $status,
            );
        }

        if ($status === 408 || $status >= 500) {
            throw new CloudConnectionException(
                message: 'Cloud provider is temporarily unavailable.',
                code: $status,
            );
        }

        throw new CloudUnexpectedResponseException(
            message: sprintf(
                'Cloud provider returned an unexpected HTTP status [%d].',
                $status,
            ),
            code: $status,
        );
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $retryAfter = trim($response->header('Retry-After'));

        if ($retryAfter === '') {
            return null;
        }

        if (ctype_digit($retryAfter)) {
            return (int) $retryAfter;
        }

        $retryAt = strtotime($retryAfter);

        if ($retryAt === false) {
            return null;
        }

        return max(0, $retryAt - time());
    }

    private function logConnectionFailure(
        string $endpoint,
        float $startedAt,
    ): void {
        Log::warning('Cloud provider connection failed.', [
            'provider' => 'arvan',
            'method' => 'GET',
            'endpoint' => $endpoint,
            'duration_ms' => $this->durationMilliseconds($startedAt),
        ]);
    }

    private function logResponse(
        string $endpoint,
        Response $response,
        float $startedAt,
    ): void {
        $context = [
            'provider' => 'arvan',
            'method' => 'GET',
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'duration_ms' => $this->durationMilliseconds($startedAt),
        ];

        $requestId = trim($response->header('X-Request-ID'));

        if ($requestId !== '') {
            $context['request_id'] = $requestId;
        }

        $rateLimitRemaining = trim(
            $response->header('X-RateLimit-Remaining'),
        );

        if ($rateLimitRemaining !== '') {
            $context['rate_limit_remaining'] = $rateLimitRemaining;
        }

        Log::debug(
            'Cloud provider request completed.',
            $context,
        );
    }

    private function durationMilliseconds(float $startedAt): float
    {
        return round(
            (microtime(true) - $startedAt) * 1000,
            2,
        );
    }
}
