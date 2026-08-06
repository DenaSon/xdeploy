<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\ArvanCloud;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudInsufficientBalanceException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;
use LogicException;

final class ArvanCloudClient
{
    private const string PROVIDER = 'arvan';

    private const string METHOD_GET = 'GET';

    private const string METHOD_POST = 'POST';

    private const string METHOD_PUT = 'PUT';

    private const string METHOD_DELETE = 'DELETE';

    private readonly string $baseUrl;

    private readonly string $apiKey;

    private readonly int $connectTimeout;

    private readonly int $requestTimeout;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $connectTimeout = 10,
        int $requestTimeout = 90,
    ) {
        $this->baseUrl = $this->normalizeBaseUrl(
            $baseUrl,
        );

        $this->apiKey = $this->normalizeApiKey(
            $apiKey,
        );

        $this->connectTimeout = $this->validateTimeout(
            timeout: $connectTimeout,
            name: 'connect timeout',
        );

        $this->requestTimeout = $this->validateTimeout(
            timeout: $requestTimeout,
            name: 'request timeout',
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
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function post(
        string $path,
        array $payload = [],
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
    public function put(
        string $path,
        array $payload = [],
    ): array {
        return $this->request(
            method: self::METHOD_PUT,
            path: $path,
            data: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function delete(
        string $path,
        array $payload = [],
    ): array {
        return $this->request(
            method: self::METHOD_DELETE,
            path: $path,
            data: $payload,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function request(
        string $method,
        string $path,
        array $data,
    ): array {
        $endpoint = $this->normalizePath(
            $path,
        );

        $url = sprintf(
            '%s/%s',
            $this->baseUrl,
            $endpoint,
        );

        $startedAt = microtime(true);

        try {
            $response = $this->sendRequest(
                request: $this->pendingRequest(),
                method: $method,
                url: $url,
                data: $data,
            );
        } catch (ConnectionException $exception) {
            $this->logConnectionFailure(
                method: $method,
                endpoint: $endpoint,
                startedAt: $startedAt,
            );

            throw new CloudConnectionException(
                message: 'Could not connect to the cloud provider.',
                previous: $exception,
            );
        }

        $this->logResponse(
            method: $method,
            endpoint: $endpoint,
            response: $response,
            startedAt: $startedAt,
        );

        if (! $response->successful()) {
            $this->throwForStatus(
                $response,
            );
        }

        return $this->decodeResponse(
            $response,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sendRequest(
        PendingRequest $request,
        string $method,
        string $url,
        array $data,
    ): Response {
        return match ($method) {
            self::METHOD_GET => $request->get(
                url: $url,
                query: $data,
            ),

            self::METHOD_POST => $request->post(
                url: $url,
                data: $data,
            ),

            self::METHOD_PUT => $request->put(
                url: $url,
                data: $data,
            ),

            self::METHOD_DELETE => $request->delete(
                url: $url,
                data: $data,
            ),

            default => throw new LogicException(
                sprintf(
                    'Unsupported cloud HTTP method [%s].',
                    $method,
                ),
            ),
        };
    }

    private function pendingRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders([
                'Authorization' => sprintf(
                    'Apikey %s',
                    $this->apiKey,
                ),
            ])
            ->connectTimeout(
                $this->connectTimeout,
            )
            ->timeout(
                $this->requestTimeout,
            )
            ->withoutRedirecting();
    }

    private function normalizeBaseUrl(
        string $baseUrl,
    ): string {
        $baseUrl = rtrim(
            trim($baseUrl),
            '/',
        );

        if ($baseUrl === '') {
            throw new CloudConfigurationException(
                'ArvanCloud base URL is not configured.',
            );
        }

        $parts = parse_url(
            $baseUrl,
        );

        if (
            $parts === false
            || strtolower(
                (string) ($parts['scheme'] ?? ''),
            ) !== 'https'
            || ! isset($parts['host'])
            || trim((string) $parts['host']) === ''
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

    private function normalizeApiKey(
        string $apiKey,
    ): string {
        $apiKey = trim(
            $apiKey,
        );

        /*
         * Environment configuration contains only the token.
         * This prevents a duplicated "Apikey" prefix.
         */
        $normalizedApiKey = preg_replace(
            '/^Apikey\s+/i',
            '',
            $apiKey,
        );

        if (! is_string($normalizedApiKey)) {
            throw new CloudConfigurationException(
                'ArvanCloud API key could not be normalized.',
            );
        }

        $normalizedApiKey = trim(
            $normalizedApiKey,
        );

        if ($normalizedApiKey === '') {
            throw new CloudConfigurationException(
                'ArvanCloud API key is not configured.',
            );
        }

        if (
            preg_match(
                '/[\x00-\x20\x7F]/',
                $normalizedApiKey,
            ) === 1
        ) {
            throw new CloudConfigurationException(
                'ArvanCloud API key contains invalid characters.',
            );
        }

        return $normalizedApiKey;
    }

    private function validateTimeout(
        int $timeout,
        string $name,
    ): int {
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

    private function normalizePath(
        string $path,
    ): string {
        $path = trim(
            $path,
        );

        if ($path === '') {
            throw new CloudValidationException(
                'Cloud provider request path cannot be empty.',
            );
        }

        if (
            str_contains($path, '\\')
            || str_contains($path, '?')
            || str_contains($path, '#')
            || preg_match(
                '/[\x00-\x20\x7F]/',
                $path,
            ) === 1
        ) {
            throw new CloudValidationException(
                'Cloud provider request path is invalid.',
            );
        }

        $path = ltrim(
            $path,
            '/',
        );

        if ($path === '') {
            throw new CloudValidationException(
                'Cloud provider request path cannot be empty.',
            );
        }

        $decodedPath = rawurldecode(
            $path,
        );

        foreach (
            explode(
                '/',
                $decodedPath,
            ) as $segment
        ) {
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
    private function decodeResponse(
        Response $response,
    ): array {
        $body = trim(
            $response->body(),
        );

        /*
         * Mutation endpoints may return 204 No Content or an
         * otherwise successful response with an empty body.
         */
        if (
            $response->status() === 204
            || $body === ''
        ) {
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

    private function throwForStatus(
        Response $response,
    ): never {
        $status = $response->status();

        if (
            in_array(
                $status,
                [
                    400,
                    409,
                    419,
                    422,
                ],
                true,
            )
        ) {
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
                retryAfterSeconds: $this->retryAfterSeconds(
                    $response,
                ),
                code: $status,
            );
        }

        if (
            $status === 408
            || $status >= 500
        ) {
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

    private function retryAfterSeconds(
        Response $response,
    ): ?int {
        $retryAfter = trim(
            (string) $response->header(
                'Retry-After',
            ),
        );

        if ($retryAfter === '') {
            return null;
        }

        if (ctype_digit($retryAfter)) {
            return (int) $retryAfter;
        }

        $retryAt = strtotime(
            $retryAfter,
        );

        if ($retryAt === false) {
            return null;
        }

        return max(
            0,
            $retryAt - time(),
        );
    }

    private function logConnectionFailure(
        string $method,
        string $endpoint,
        float $startedAt,
    ): void {
        Log::warning(
            'Cloud provider connection failed.',
            [
                'provider' => self::PROVIDER,
                'method' => $method,
                'endpoint' => $endpoint,
                'duration_ms' => $this->durationMilliseconds(
                    $startedAt,
                ),
            ],
        );
    }

    private function logResponse(
        string $method,
        string $endpoint,
        Response $response,
        float $startedAt,
    ): void {
        /*
         * Payload and response body are deliberately excluded.
         * Create responses can contain generated passwords.
         */
        $context = [
            'provider' => self::PROVIDER,
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'duration_ms' => $this->durationMilliseconds(
                $startedAt,
            ),
        ];

        $requestId = trim(
            (string) $response->header(
                'X-Request-ID',
            ),
        );

        if ($requestId !== '') {
            $context['request_id'] = $requestId;
        }

        $rateLimitRemaining = trim(
            (string) $response->header(
                'X-RateLimit-Remaining',
            ),
        );

        if ($rateLimitRemaining !== '') {
            $context['rate_limit_remaining'] =
                $rateLimitRemaining;
        }

        Log::debug(
            'Cloud provider request completed.',
            $context,
        );
    }

    private function durationMilliseconds(
        float $startedAt,
    ): float {
        return round(
            (microtime(true) - $startedAt) * 1_000,
            2,
        );
    }
}
