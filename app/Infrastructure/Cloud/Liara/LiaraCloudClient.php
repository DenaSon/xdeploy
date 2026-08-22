<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Liara;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudInsufficientBalanceException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Infrastructure\Cloud\Transport\CloudProviderRetryPolicy;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use LogicException;
use SensitiveParameter;
use Throwable;

final class LiaraCloudClient
{
    private const string METHOD_GET = 'GET';

    private const string METHOD_POST = 'POST';

    private const string METHOD_PATCH = 'PATCH';

    private const string METHOD_DELETE = 'DELETE';

    private const int MAX_PROVIDER_ERROR_LENGTH = 300;

    private readonly string $baseUrl;

    private readonly string $apiToken;

    private readonly int $connectTimeout;

    private readonly int $requestTimeout;

    private readonly int $catalogConnectTimeout;

    private readonly int $catalogRequestTimeout;

    private readonly int $retryMaxAttempts;

    private readonly int $retryDelayMilliseconds;

    public function __construct(
        string $baseUrl,
        #[SensitiveParameter]
        string $apiToken,
        int $connectTimeout = 10,
        int $requestTimeout = 90,
        int $catalogConnectTimeout = 3,
        int $catalogRequestTimeout = 8,
        int $retryMaxAttempts = 1,
        int $retryDelayMilliseconds = 250,
    ) {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
        $this->apiToken = $this->normalizeApiToken($apiToken);
        $this->connectTimeout = $this->validateTimeout(
            timeout: $connectTimeout,
            name: 'connect timeout',
        );
        $this->requestTimeout = $this->validateTimeout(
            timeout: $requestTimeout,
            name: 'request timeout',
        );
        $this->catalogConnectTimeout = $this->validateTimeout(
            timeout: $catalogConnectTimeout,
            name: 'catalog connect timeout',
        );
        $this->catalogRequestTimeout = $this->validateTimeout(
            timeout: $catalogRequestTimeout,
            name: 'catalog request timeout',
        );
        $this->retryMaxAttempts = $this->validateRetryMaxAttempts(
            $retryMaxAttempts,
        );
        $this->retryDelayMilliseconds = $this->validateRetryDelay(
            $retryDelayMilliseconds,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>
     */
    public function get(
        string $path,
        array $query = [],
    ): array {
        return $this->request(
            method: self::METHOD_GET,
            path: $path,
            data: $query,
            retrySafe: true,
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>
     */
    public function getCatalog(
        string $path,
        array $query = [],
    ): array {
        return $this->request(
            method: self::METHOD_GET,
            path: $path,
            data: $query,
            connectTimeout: $this->catalogConnectTimeout,
            requestTimeout: $this->catalogRequestTimeout,
            retrySafe: true,
        );
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<array-key, mixed>
     */
    public function post(
        string $path,
        ?array $payload = null,
    ): array {
        return $this->request(
            method: self::METHOD_POST,
            path: $path,
            data: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function patch(
        string $path,
        array $payload = [],
    ): array {
        return $this->request(
            method: self::METHOD_PATCH,
            path: $path,
            data: $payload,
        );
    }

    /**
     * @return array<array-key, mixed>
     */
    public function delete(
        string $path,
    ): array {
        return $this->request(
            method: self::METHOD_DELETE,
            path: $path,
            data: null,
        );
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<array-key, mixed>
     */
    private function request(
        string $method,
        string $path,
        ?array $data,
        ?int $connectTimeout = null,
        ?int $requestTimeout = null,
        bool $retrySafe = false,
    ): array {
        $endpoint = $this->normalizePath($path);
        $url = sprintf('%s/%s', $this->baseUrl, $endpoint);

        try {
            $response = $this->sendRequest(
                request: $this->pendingRequest(
                    connectTimeout: $connectTimeout,
                    requestTimeout: $requestTimeout,
                    retrySafe: $retrySafe,
                ),
                method: $method,
                url: $url,
                data: $data,
            );
        } catch (ConnectionException $exception) {
            throw new CloudConnectionException(
                message: 'Could not connect to the cloud provider.',
                previous: $exception,
            );
        }

        if (! $response->successful()) {
            $this->throwForStatus($response);
        }

        return $this->decodeResponse($response);
    }

    /**
     * @param  array<string, mixed>|null  $data
     */
    private function sendRequest(
        PendingRequest $request,
        string $method,
        string $url,
        ?array $data,
    ): Response {
        return match ($method) {
            self::METHOD_GET => $request->get(
                url: $url,
                query: $data ?? [],
            ),

            self::METHOD_POST => $data === null
                ? $request->send(
                    method: self::METHOD_POST,
                    url: $url,
                )
                : $request->asJson()->post(
                    url: $url,
                    data: $data,
                ),

            self::METHOD_PATCH => $request->asJson()->patch(
                url: $url,
                data: $data ?? [],
            ),

            self::METHOD_DELETE => $request->delete($url),

            default => throw new LogicException(
                sprintf('Unsupported cloud HTTP method [%s].', $method),
            ),
        };
    }

    private function pendingRequest(
        ?int $connectTimeout = null,
        ?int $requestTimeout = null,
        bool $retrySafe = false,
    ): PendingRequest {
        $request = Http::acceptJson()
            ->withToken($this->apiToken)
            ->connectTimeout(
                $connectTimeout
                    ?? $this->connectTimeout,
            )
            ->timeout(
                $requestTimeout
                    ?? $this->requestTimeout,
            )
            ->withoutRedirecting();

        if (! $retrySafe || $this->retryMaxAttempts === 1) {
            return $request;
        }

        return $request->retry(
            times: $this->retryMaxAttempts,
            sleepMilliseconds: $this->retryDelayMilliseconds,
            when: static fn (?Throwable $exception): bool => CloudProviderRetryPolicy::shouldRetry($exception),
            throw: false,
        );
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');

        if ($baseUrl === '') {
            throw new CloudConfigurationException(
                'Liara base URL is not configured.',
            );
        }

        $parts = parse_url($baseUrl);

        if (
            $parts === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || trim((string) $parts['host']) === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
        ) {
            throw new CloudConfigurationException(
                'Liara base URL must be a valid HTTPS URL.',
            );
        }

        return $baseUrl;
    }

    private function normalizeApiToken(
        #[SensitiveParameter]
        string $apiToken,
    ): string {
        $apiToken = trim($apiToken);

        $normalized = preg_replace(
            '/^Bearer\s+/i',
            '',
            $apiToken,
        );

        if (! is_string($normalized)) {
            throw new CloudConfigurationException(
                'Liara API token could not be normalized.',
            );
        }

        $normalized = trim($normalized);

        if ($normalized === '') {
            throw new CloudConfigurationException(
                'Liara API token is not configured.',
            );
        }

        if (preg_match('/[\x00-\x20\x7F]/', $normalized) === 1) {
            throw new CloudConfigurationException(
                'Liara API token contains invalid characters.',
            );
        }

        return $normalized;
    }

    private function validateTimeout(
        int $timeout,
        string $name,
    ): int {
        if ($timeout < 1) {
            throw new CloudConfigurationException(
                sprintf('Liara %s must be greater than zero.', $name),
            );
        }

        return $timeout;
    }

    private function validateRetryMaxAttempts(int $attempts): int
    {
        if ($attempts < 1) {
            throw new CloudConfigurationException(
                'Liara retry max attempts must be at least one.',
            );
        }

        return $attempts;
    }

    private function validateRetryDelay(int $milliseconds): int
    {
        if ($milliseconds < 0) {
            throw new CloudConfigurationException(
                'Liara retry delay must not be negative.',
            );
        }

        return $milliseconds;
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

        if ($path === '') {
            throw new CloudValidationException(
                'Cloud provider request path cannot be empty.',
            );
        }

        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
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
        $body = trim($response->body());

        if ($response->status() === 204 || $body === '') {
            return [];
        }

        try {
            $payload = json_decode(
                json: $body,
                associative: true,
                depth: 512,
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

        if (in_array($status, [400, 409, 412, 419, 422, 428], true)) {
            $providerMessage = $this->providerErrorMessage($response);

            throw new CloudValidationException(
                message: $providerMessage === null
                    ? 'Cloud provider rejected the request.'
                    : sprintf(
                        'Cloud provider rejected the request: %s',
                        $providerMessage,
                    ),
                code: $status,
            );
        }

        if ($status === 401) {
            throw new CloudAuthenticationException(
                message: 'Cloud provider authentication failed.',
                code: $status,
            );
        }

        if ($status === 402) {
            throw new CloudInsufficientBalanceException(
                message: 'Cloud provider account balance is insufficient.',
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

    private function providerErrorMessage(Response $response): ?string
    {
        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        foreach (['message', 'error'] as $field) {
            $value = $payload[$field] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $normalized = preg_replace('/\s+/', ' ', $value);

            if (! is_string($normalized) || $normalized === '') {
                continue;
            }

            return substr($normalized, 0, self::MAX_PROVIDER_ERROR_LENGTH);
        }

        return null;
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $retryAfter = trim((string) $response->header('Retry-After'));

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
}
