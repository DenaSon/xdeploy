<?php

declare(strict_types=1);

namespace App\Application\Integrations\Cloudflare;

use App\Domain\Integration\Cloudflare\CloudflareDnsRecordTypes;
use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use App\Models\IntegrationConnection;

final readonly class CloudflareDnsService
{
    public function __construct(
        private CloudflareApiClient $api,
        private CloudflareAccessTokenService $accessTokens,
    ) {}

    public function manageable(
        IntegrationConnection $connection,
    ): bool {
        return $this->accessTokens->hasScopes(
            $connection,
            CloudflareScopes::dnsWrite(),
        );
    }

    /** @return array<string, mixed> */
    public function create(
        IntegrationConnection $connection,
        string $zoneId,
        string $zoneName,
        string $type,
        string $name,
        string $content,
        int $ttl,
        bool $proxied,
        ?int $priority,
        ?string $comment,
    ): array {
        return $this->api->createDnsRecord(
            $this->token($connection),
            $zoneId,
            $this->payload(
                zoneName: $zoneName,
                type: $type,
                name: $name,
                content: $content,
                ttl: $ttl,
                proxied: $proxied,
                priority: $priority,
                comment: $comment,
            ),
        );
    }

    /** @return array<string, mixed> */
    public function update(
        IntegrationConnection $connection,
        string $zoneId,
        string $zoneName,
        string $recordId,
        string $type,
        string $name,
        string $content,
        int $ttl,
        bool $proxied,
        ?int $priority,
        ?string $comment,
    ): array {
        return $this->api->updateDnsRecord(
            $this->token($connection),
            $zoneId,
            $recordId,
            $this->payload(
                zoneName: $zoneName,
                type: $type,
                name: $name,
                content: $content,
                ttl: $ttl,
                proxied: $proxied,
                priority: $priority,
                comment: $comment,
            ),
        );
    }

    public function delete(
        IntegrationConnection $connection,
        string $zoneId,
        string $recordId,
    ): void {
        $this->api->deleteDnsRecord(
            $this->token($connection),
            $zoneId,
            $recordId,
        );
    }

    private function token(
        IntegrationConnection $connection,
    ): string {
        return $this->accessTokens->token(
            $connection,
            CloudflareScopes::dnsWrite(),
        );
    }

    /** @return array<string, mixed> */
    private function payload(
        string $zoneName,
        string $type,
        string $name,
        string $content,
        int $ttl,
        bool $proxied,
        ?int $priority,
        ?string $comment,
    ): array {
        $type = strtoupper(trim($type));
        $zoneName = strtolower(trim($zoneName, " .\t\n\r\0\x0B"));

        if (! CloudflareDnsRecordTypes::supports($type)) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_REQUEST,
                'Cloudflare DNS record type is not supported for management.',
            );
        }

        if ($zoneName === '') {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_REQUEST,
                'Cloudflare zone name is missing.',
            );
        }

        if ($ttl !== 1 && ($ttl < 60 || $ttl > 86400)) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_REQUEST,
                'Cloudflare DNS TTL is invalid.',
            );
        }

        $name = $this->recordName(
            $name,
            $zoneName,
        );
        $content = trim($content);

        if ($content === '') {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_REQUEST,
                'Cloudflare DNS record content is missing.',
            );
        }

        $payload = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
        ];

        if (CloudflareDnsRecordTypes::proxiable($type)) {
            $payload['proxied'] = $proxied;
        }

        if (CloudflareDnsRecordTypes::requiresPriority($type)) {
            if ($priority === null || $priority < 0 || $priority > 65535) {
                throw new CloudflareApiException(
                    CloudflareApiException::INVALID_REQUEST,
                    'Cloudflare DNS record priority is invalid.',
                );
            }

            $payload['priority'] = $priority;
        }

        $comment = is_string($comment)
            ? trim($comment)
            : '';

        if ($comment !== '') {
            $payload['comment'] = $comment;
        }

        return $payload;
    }

    private function recordName(
        string $name,
        string $zoneName,
    ): string {
        $name = strtolower(trim($name, " .\t\n\r\0\x0B"));

        if ($name === '' || $name === '@') {
            return $zoneName;
        }

        if (
            $name === $zoneName
            || str_ends_with($name, '.'.$zoneName)
        ) {
            return $name;
        }

        return $name.'.'.$zoneName;
    }
}
