<?php

declare(strict_types=1);

namespace App\Application\Integrations\Cloudflare;

use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Models\IntegrationConnection;

final readonly class CloudflareReadService
{
    public function __construct(
        private CloudflareApiClient $api,
        private CloudflareAccessTokenService $accessTokens,
    ) {}

    /**
     * @return array{
     *     accounts: list<array{id: string, name: string}>,
     *     zones: list<array<string, mixed>>
     * }
     */
    public function snapshot(
        IntegrationConnection $connection,
    ): array {
        $accessToken = $this->accessTokens->token(
            $connection,
            CloudflareScopes::read(),
        );

        $accounts = $this->api->accounts($accessToken);
        $zones = $this->api->zones($accessToken);

        $connection->forceFill([
            'last_synced_at' => now(),
        ])->save();

        return [
            'accounts' => $accounts,
            'zones' => $zones,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function dnsRecords(
        IntegrationConnection $connection,
        string $zoneId,
    ): array {
        return $this->api->dnsRecords(
            $this->accessTokens->token(
                $connection,
                CloudflareScopes::read(),
            ),
            $zoneId,
        );
    }

    public function readable(
        IntegrationConnection $connection,
    ): bool {
        return $this->accessTokens->hasScopes(
            $connection,
            CloudflareScopes::read(),
        );
    }
}
