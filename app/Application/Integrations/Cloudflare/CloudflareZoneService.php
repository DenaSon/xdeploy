<?php

declare(strict_types=1);

namespace App\Application\Integrations\Cloudflare;

use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Domain\Shared\Exceptions\InvalidPublicDomainNameException;
use App\Domain\Shared\ValueObjects\PublicDomainName;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use App\Models\IntegrationConnection;

final readonly class CloudflareZoneService
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
            CloudflareScopes::zoneWrite(),
        );
    }

    /** @return array<string, mixed> */
    public function create(
        IntegrationConnection $connection,
        string $accountId,
        string $domain,
    ): array {
        $domainName = $this->domainName($domain);

        $token = $this->accessTokens->token(
            $connection,
            [
                CloudflareScopes::ACCOUNT_SETTINGS_READ,
                ...CloudflareScopes::zoneWrite(),
            ],
        );

        $this->ensureAccessibleAccount(
            $token,
            $accountId,
        );

        return $this->api->createZone(
            accessToken: $token,
            accountId: $accountId,
            name: $domainName->value,
        );
    }

    /** @return array<string, mixed> */
    public function get(
        IntegrationConnection $connection,
        string $zoneId,
    ): array {
        return $this->api->zone(
            $this->accessTokens->token(
                $connection,
                [CloudflareScopes::ZONE_READ],
            ),
            $zoneId,
        );
    }

    /** @return array<string, mixed> */
    public function refresh(
        IntegrationConnection $connection,
        string $zoneId,
    ): array {
        return $this->get(
            $connection,
            $zoneId,
        );
    }

    public function delete(
        IntegrationConnection $connection,
        string $zoneId,
    ): void {
        $token = $this->accessTokens->token(
            $connection,
            [
                CloudflareScopes::ZONE_READ,
                ...CloudflareScopes::zoneWrite(),
            ],
        );

        // Resolve the zone through the same user-bound OAuth connection before
        // issuing a destructive mutation. The remote API remains the source of
        // truth and enforces the Cloudflare-side authorization boundary.
        $this->api->zone(
            $token,
            $zoneId,
        );

        $this->api->deleteZone(
            $token,
            $zoneId,
        );
    }

    private function domainName(string $domain): PublicDomainName
    {
        try {
            return PublicDomainName::from($domain);
        } catch (InvalidPublicDomainNameException) {
            throw new CloudflareApiException(
                CloudflareApiException::INVALID_REQUEST,
                'Cloudflare zone domain is invalid.',
            );
        }
    }

    private function ensureAccessibleAccount(
        string $accessToken,
        string $accountId,
    ): void {
        foreach ($this->api->accounts($accessToken) as $account) {
            if (($account['id'] ?? null) === $accountId) {
                return;
            }
        }

        throw new CloudflareApiException(
            CloudflareApiException::INVALID_REQUEST,
            'Cloudflare account is not accessible through this connection.',
        );
    }
}
