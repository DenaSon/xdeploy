<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Cloudflare;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use SensitiveParameter;

final class CloudflareApiClient
{
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
     * @return list<array{
     *     id: string,
     *     name: string,
     *     status: string,
     *     paused: bool,
     *     type: string,
     *     development_mode: int,
     *     account: array{id: string, name: string}|null,
     *     name_servers: list<string>
     * }>
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

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->get(
                $this->url($path),
                $query,
            );

        return $this->validated($response);
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

        $request = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout());

        $options = $payload === []
            ? []
            : ['json' => $payload];

        return $this->validated(
            $request->send(
                strtoupper($method),
                $this->url($path),
                $options,
            ),
        );
    }

    private function validated(Response $response): Response
    {
        if ($response->status() === 401) {
            throw new CloudflareApiException(
                CloudflareApiException::UNAUTHORIZED,
                'Cloudflare rejected the access token.',
            );
        }

        if ($response->status() === 403) {
            throw new CloudflareApiException(
                CloudflareApiException::FORBIDDEN,
                'Cloudflare rejected the requested permission.',
            );
        }

        if ($response->status() === 429) {
            throw new CloudflareApiException(
                CloudflareApiException::RATE_LIMITED,
                'Cloudflare rate limit reached.',
            );
        }

        if (! $response->successful()) {
            throw new CloudflareApiException(
                CloudflareApiException::REMOTE_ERROR,
                'Cloudflare API request failed.',
            );
        }

        if ($response->json('success') === false) {
            throw new CloudflareApiException(
                CloudflareApiException::REMOTE_ERROR,
                'Cloudflare API reported an unsuccessful response.',
            );
        }

        return $response;
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

        $nameServers = [];

        if (is_array($item['name_servers'] ?? null)) {
            foreach ($item['name_servers'] as $nameServer) {
                $nameServer = $this->string($nameServer);

                if ($nameServer !== null) {
                    $nameServers[] = $nameServer;
                }
            }
        }

        return [
            'id' => $id,
            'name' => $name,
            'status' => $this->string($item['status'] ?? null) ?? 'unknown',
            'paused' => (bool) ($item['paused'] ?? false),
            'type' => $this->string($item['type'] ?? null) ?? 'unknown',
            'development_mode' => is_numeric($item['development_mode'] ?? null)
                ? max(0, (int) $item['development_mode'])
                : 0,
            'account' => $account,
            'name_servers' => array_values(array_unique($nameServers)),
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
