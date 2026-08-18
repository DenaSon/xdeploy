<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Cloudflare;

use App\Domain\Integration\Cloudflare\CloudflareZoneStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

final class CloudflareApiClient
{
    private const READ_ATTEMPTS = 2;

    private const READ_RETRY_DELAY_MICROSECONDS = 200_000;

    /**
     * @return list<array{id: string, name: string}>
     */
    public function accounts(
        #[SensitiveParameter]
        string $accessToken,
    ): array {
        return array_values(
            array_filter(
                array_map(
                    $this->normalizeAccount(...),
                    $this->collect(
                        path: '/accounts',
                        accessToken: $accessToken,
                        perPage: 50,
                    ),
                ),
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function zones(
        #[SensitiveParameter]
        string $accessToken,
    ): array {
        return array_values(
            array_filter(
                array_map(
                    $this->normalizeZone(...),
                    $this->collect(
                        path: '/zones',
                        accessToken: $accessToken,
                        perPage: 50,
                    ),
                ),
            ),
        );
    }

    /** @return array<string, mixed> */
    public function zone(
        #[SensitiveParameter]
        string $accessToken,
        string $zoneId,
    ): array {
        $this->ensureIdentifier(
            $zoneId,
            'Cloudflare zone identifier is invalid.',
        );

        return $this->zoneMutation(
            $this->get(
                path: "/zones/{$zoneId}",
                accessToken: $accessToken,
                query: [],
            ),
        );
    }

    /** @return array<string, mixed> */
    public function createZone(
        #[SensitiveParameter]
        string $accessToken,
        string $accountId,
        string $name,
    ): array {
        $this->ensureRequestIdentifier(
            $accountId,
            'Cloudflare account identifier is invalid.',
        );

        $name = strtolower(trim($name, " .\t\n\r\0\x0B"));

        if ($name === '' || strlen($name) > 253) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_REQUEST,
                'Cloudflare zone name is invalid.',
            );
        }

        return $this->zoneMutation(
            $this->mutate(
                method: 'POST',
                path: '/zones',
                accessToken: $accessToken,
                payload: [
                    'account' => ['id' => $accountId],
                    'name' => $name,
                    'type' => 'full',
                ],
            ),
        );
    }

    public function deleteZone(
        #[SensitiveParameter]
        string $accessToken,
        string $zoneId,
    ): void {
        $this->ensureIdentifier(
            $zoneId,
            'Cloudflare zone identifier is invalid.',
        );

        $response = $this->mutate(
            method: 'DELETE',
            path: "/zones/{$zoneId}",
            accessToken: $accessToken,
        );

        $deletedId = $this->string(
            $response->json('result.id'),
        );

        if ($deletedId === null || ! hash_equals($zoneId, $deletedId)) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_RESPONSE,
                'Cloudflare zone delete response was invalid.',
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dnsRecords(
        #[SensitiveParameter]
        string $accessToken,
        string $zoneId,
    ): array {
        $this->ensureIdentifier(
            $zoneId,
            'Cloudflare zone identifier is invalid.',
        );

        return array_values(
            array_filter(
                array_map(
                    $this->normalizeDnsRecord(...),
                    $this->collect(
                        path: "/zones/{$zoneId}/dns_records",
                        accessToken: $accessToken,
                        perPage: 100,
                        query: [
                            'order' => 'name',
                            'direction' => 'asc',
                        ],
                    ),
                ),
            ),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createDnsRecord(
        #[SensitiveParameter]
        string $accessToken,
        string $zoneId,
        array $payload,
    ): array {
        $this->ensureIdentifier(
            $zoneId,
            'Cloudflare zone identifier is invalid.',
        );

        return $this->dnsRecordMutation(
            response: $this->mutate(
                method: 'POST',
                path: "/zones/{$zoneId}/dns_records",
                accessToken: $accessToken,
                payload: $payload,
            ),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateDnsRecord(
        #[SensitiveParameter]
        string $accessToken,
        string $zoneId,
        string $recordId,
        array $payload,
    ): array {
        $this->ensureIdentifier(
            $zoneId,
            'Cloudflare zone identifier is invalid.',
        );
        $this->ensureIdentifier(
            $recordId,
            'Cloudflare DNS record identifier is invalid.',
        );

        return $this->dnsRecordMutation(
            response: $this->mutate(
                method: 'PATCH',
                path: "/zones/{$zoneId}/dns_records/{$recordId}",
                accessToken: $accessToken,
                payload: $payload,
            ),
        );
    }

    public function deleteDnsRecord(
        #[SensitiveParameter]
        string $accessToken,
        string $zoneId,
        string $recordId,
    ): void {
        $this->ensureIdentifier(
            $zoneId,
            'Cloudflare zone identifier is invalid.',
        );
        $this->ensureIdentifier(
            $recordId,
            'Cloudflare DNS record identifier is invalid.',
        );

        $response = $this->mutate(
            method: 'DELETE',
            path: "/zones/{$zoneId}/dns_records/{$recordId}",
            accessToken: $accessToken,
        );

        $deletedId = $this->string(
            $response->json('result.id'),
        );

        if ($deletedId === null || ! hash_equals($recordId, $deletedId)) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_RESPONSE,
                'Cloudflare DNS delete response was invalid.',
            );
        }
    }

    /**
     * @param array<string, scalar> $query
     * @return list<array<string, mixed>>
     */
    private function collect(
        string $path,
        #[SensitiveParameter]
        string $accessToken,
        int $perPage,
        array $query = [],
    ): array {
        $items = [];
        $page = 1;
        $maxPages = $this->maxPages();

        do {
            $response = $this->get(
                path: $path,
                accessToken: $accessToken,
                query: [
                    ...$query,
                    'page' => $page,
                    'per_page' => $perPage,
                ],
            );

            $result = $response->json('result');

            if (! is_array($result)) {
                throw new CloudflareApiException(
                    CloudflareApiException::INVALID_RESPONSE,
                    'Cloudflare API response did not contain a collection.',
                );
            }

            foreach ($result as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }

            $reportedTotalPages = $response->json(
                'result_info.total_pages',
            );
            $reportedTotalCount = $response->json(
                'result_info.total_count',
            );
            $reportedPerPage = $response->json(
                'result_info.per_page',
            );

            if (is_numeric($reportedTotalPages)) {
                $totalPages = max(1, (int) $reportedTotalPages);
            } elseif (
                is_numeric($reportedTotalCount)
                && is_numeric($reportedPerPage)
                && (int) $reportedPerPage > 0
            ) {
                $totalPages = max(
                    1,
                    (int) ceil(
                        (int) $reportedTotalCount
                        / (int) $reportedPerPage,
                    ),
                );
            } else {
                $totalPages = $page;
            }

            if ($totalPages > $maxPages) {
                throw new CloudflareApiException(
                    CloudflareApiException::RESOURCE_LIMIT,
                    'Cloudflare collection exceeds the configured page limit.',
                );
            }

            $page++;
        } while ($page <= $totalPages);

        return $items;
    }

    /**
     * @param array<string, scalar> $query
     */
    private function get(
        string $path,
        #[SensitiveParameter]
        string $accessToken,
        array $query,
    ): Response {
        $this->ensureAccessToken($accessToken);

        for ($attempt = 1; $attempt <= self::READ_ATTEMPTS; $attempt++) {
            try {
                $response = Http::withToken($accessToken)
                    ->acceptJson()
                    ->connectTimeout($this->connectTimeout())
                    ->timeout($this->timeout())
                    ->get(
                        $this->url($path),
                        $query,
                    );

                return $this->validated(
                    response: $response,
                    method: 'GET',
                    path: $path,
                );
            } catch (ConnectionException $exception) {
                if ($attempt === self::READ_ATTEMPTS) {
                    throw $this->connectionFailure(
                        exception: $exception,
                        method: 'GET',
                        path: $path,
                    );
                }

                usleep(self::READ_RETRY_DELAY_MICROSECONDS);
            }
        }

        throw new CloudflareApiException(
            CloudflareApiException::CONNECTION_FAILED,
            'Cloudflare API connection failed.',
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function mutate(
        string $method,
        string $path,
        #[SensitiveParameter]
        string $accessToken,
        array $payload = [],
    ): Response {
        $this->ensureAccessToken($accessToken);

        $method = strtoupper($method);

        $request = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout());

        $options = $payload === []
            ? []
            : ['json' => $payload];

        try {
            return $this->validated(
                response: $request->send(
                    $method,
                    $this->url($path),
                    $options,
                ),
                method: $method,
                path: $path,
            );
        } catch (ConnectionException $exception) {
            throw $this->connectionFailure(
                exception: $exception,
                method: $method,
                path: $path,
            );
        }
    }

    private function connectionFailure(
        ConnectionException $exception,
        string $method,
        string $path,
    ): CloudflareApiException {
        Log::warning(
            'cloudflare.api.connection_failed',
            [
                'method' => strtoupper($method),
                'path' => $path,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ],
        );

        return new CloudflareApiException(
            CloudflareApiException::CONNECTION_FAILED,
            'Cloudflare API connection failed.',
            $exception,
        );
    }

    private function validated(
        Response $response,
        string $method,
        string $path,
    ): Response {
        if (
            $response->successful()
            && $response->json('success') !== false
        ) {
            return $response;
        }

        [$remoteCode, $remoteMessage] = $this->remoteError($response);
        $reason = $this->errorReason($response->status());

        Log::warning(
            'cloudflare.api.request_failed',
            array_filter(
                [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'http_status' => $response->status(),
                    'remote_code' => $remoteCode,
                    'remote_message' => $remoteMessage,
                ],
                static fn (mixed $value): bool => $value !== null,
            ),
        );

        throw new CloudflareApiException(
            reason: $reason,
            message: $this->errorMessage($reason),
            httpStatus: $response->status(),
            remoteCode: $remoteCode,
            remoteMessage: $remoteMessage,
        );
    }

    /** @return array{0: int|string|null, 1: string|null} */
    private function remoteError(Response $response): array
    {
        $errors = $response->json('errors');

        if (! is_array($errors)) {
            return [null, null];
        }

        foreach ($errors as $error) {
            if (! is_array($error)) {
                continue;
            }

            $code = $error['code'] ?? null;

            if (! is_int($code) && ! is_string($code)) {
                $code = null;
            }

            if (is_string($code)) {
                $code = trim($code);
                $code = $code === '' ? null : $code;
            }

            $message = $this->string($error['message'] ?? null);

            if ($message !== null) {
                $message = preg_replace('/[\r\n\t]+/u', ' ', $message)
                    ?? $message;
                $message = substr($message, 0, 1000);
            }

            if ($code !== null || $message !== null) {
                return [$code, $message];
            }
        }

        return [null, null];
    }

    private function errorReason(int $status): string
    {
        return match ($status) {
            401 => CloudflareApiException::UNAUTHORIZED,
            403 => CloudflareApiException::FORBIDDEN,
            429 => CloudflareApiException::RATE_LIMITED,
            400, 404, 409, 422 => CloudflareApiException::INVALID_REQUEST,
            default => CloudflareApiException::REMOTE_ERROR,
        };
    }

    private function errorMessage(string $reason): string
    {
        return match ($reason) {
            CloudflareApiException::UNAUTHORIZED => 'Cloudflare rejected the access token.',
            CloudflareApiException::FORBIDDEN => 'Cloudflare rejected the requested permission.',
            CloudflareApiException::RATE_LIMITED => 'Cloudflare rate limit reached.',
            CloudflareApiException::INVALID_REQUEST => 'Cloudflare rejected the request.',
            default => 'Cloudflare API request failed.',
        };
    }

    /** @return array<string, mixed> */
    private function zoneMutation(Response $response): array
    {
        $result = $response->json('result');

        if (! is_array($result)) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_RESPONSE,
                'Cloudflare zone response did not contain a zone.',
            );
        }

        $zone = $this->normalizeZone($result);

        if ($zone === null) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_RESPONSE,
                'Cloudflare zone response was invalid.',
            );
        }

        return $zone;
    }

    /** @return array<string, mixed> */
    private function dnsRecordMutation(Response $response): array
    {
        $result = $response->json('result');

        if (! is_array($result)) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_RESPONSE,
                'Cloudflare DNS mutation response did not contain a record.',
            );
        }

        $record = $this->normalizeDnsRecord($result);

        if ($record === null) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_RESPONSE,
                'Cloudflare DNS mutation response was invalid.',
            );
        }

        return $record;
    }

    /** @param array<string, mixed> $item */
    private function normalizeAccount(array $item): ?array
    {
        $id = $this->string($item['id'] ?? null);
        $name = $this->string($item['name'] ?? null);

        if ($id === null || $name === null) {
            return null;
        }

        return [
            'id' => $id,
            'name' => $name,
        ];
    }

    /** @param array<string, mixed> $item */
    private function normalizeZone(array $item): ?array
    {
        $id = $this->string($item['id'] ?? null);
        $name = $this->string($item['name'] ?? null);

        if ($id === null || $name === null) {
            return null;
        }

        $account = is_array($item['account'] ?? null)
            ? $this->normalizeAccount($item['account'])
            : null;

        return [
            'id' => $id,
            'name' => strtolower($name),
            'status' => CloudflareZoneStatus::fromRemote(
                $item['status'] ?? null,
            )->value,
            'paused' => (bool) ($item['paused'] ?? false),
            'type' => $this->string($item['type'] ?? null) ?? 'unknown',
            'development_mode' => is_numeric($item['development_mode'] ?? null)
                ? max(0, (int) $item['development_mode'])
                : 0,
            'account' => $account,
            'name_servers' => $this->stringList(
                $item['name_servers'] ?? null,
            ),
            'original_name_servers' => $this->stringList(
                $item['original_name_servers'] ?? null,
            ),
            'created_on' => $this->string($item['created_on'] ?? null),
            'activated_on' => $this->string($item['activated_on'] ?? null),
        ];
    }

    /** @param array<string, mixed> $item */
    private function normalizeDnsRecord(array $item): ?array
    {
        $id = $this->string($item['id'] ?? null);
        $type = $this->string($item['type'] ?? null);
        $name = $this->string($item['name'] ?? null);
        $content = $this->string($item['content'] ?? null);

        if (
            $id === null
            || $type === null
            || $name === null
            || $content === null
        ) {
            return null;
        }

        $proxied = $item['proxied'] ?? null;
        $proxiable = $item['proxiable'] ?? null;
        $priority = $item['priority'] ?? null;

        return [
            'id' => $id,
            'type' => strtoupper($type),
            'name' => $name,
            'content' => $content,
            'proxied' => is_bool($proxied) ? $proxied : null,
            'proxiable' => is_bool($proxiable) ? $proxiable : false,
            'ttl' => is_numeric($item['ttl'] ?? null)
                ? max(1, (int) $item['ttl'])
                : 1,
            'priority' => is_numeric($priority)
                ? (int) $priority
                : null,
            'comment' => $this->string($item['comment'] ?? null),
            'modified_on' => $this->string($item['modified_on'] ?? null),
        ];
    }

    private function ensureAccessToken(
        #[SensitiveParameter]
        string $accessToken,
    ): void {
        if (trim($accessToken) !== '') {
            return;
        }

        throw new CloudflareApiException(
            CloudflareApiException::UNAUTHORIZED,
            'Cloudflare access token is missing.',
        );
    }

    private function ensureIdentifier(
        string $identifier,
        string $message,
    ): void {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $identifier) === 1) {
            return;
        }

        throw new CloudflareApiException(
            CloudflareApiException::INVALID_RESPONSE,
            $message,
        );
    }

    private function ensureRequestIdentifier(
        string $identifier,
        string $message,
    ): void {
        if (preg_match('/\A[a-f0-9]{32}\z/i', $identifier) === 1) {
            return;
        }

        throw new CloudflareApiException(
            CloudflareApiException::INVALID_REQUEST,
            $message,
        );
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = $this->string($item);

            if ($item !== null) {
                $items[] = strtolower($item);
            }
        }

        return array_values(array_unique($items));
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl(), '/')
            .'/'.ltrim($path, '/');
    }

    private function baseUrl(): string
    {
        return (string) config(
            'services.cloudflare_api.base_url',
            'https://api.cloudflare.com/client/v4',
        );
    }

    private function connectTimeout(): int
    {
        return max(
            1,
            (int) config(
                'services.cloudflare_api.connect_timeout',
                5,
            ),
        );
    }

    private function timeout(): int
    {
        return max(
            1,
            (int) config(
                'services.cloudflare_api.timeout',
                15,
            ),
        );
    }

    private function maxPages(): int
    {
        return max(
            1,
            min(
                50,
                (int) config(
                    'services.cloudflare_api.max_pages',
                    20,
                ),
            ),
        );
    }
}
