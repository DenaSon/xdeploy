<?php

declare(strict_types=1);

namespace App\Application\Integrations\Cloudflare;

use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiClient;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use App\Infrastructure\Integrations\Cloudflare\CloudflareOAuthClient;
use App\Models\IntegrationConnection;
use SensitiveParameter;
use Throwable;

final readonly class CloudflareReadService
{
    private const REFRESH_SKEW_SECONDS = 60;

    public function __construct(
        private CloudflareApiClient $api,
        private CloudflareOAuthClient $oauth,
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
        $this->ensureReadable($connection);
        $accessToken = $this->accessToken($connection);

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
        $this->ensureReadable($connection);

        return $this->api->dnsRecords(
            $this->accessToken($connection),
            $zoneId,
        );
    }

    public function readable(
        IntegrationConnection $connection,
    ): bool {
        $scopes = is_array($connection->scopes)
            ? $connection->scopes
            : [];

        return CloudflareScopes::missing($scopes) === [];
    }

    private function ensureReadable(
        IntegrationConnection $connection,
    ): void {
        if ($this->readable($connection)) {
            return;
        }

        throw new CloudflareApiException(
            CloudflareApiException::MISSING_SCOPES,
            'Cloudflare connection is missing read scopes.',
        );
    }

    private function accessToken(
        IntegrationConnection $connection,
    ): string {
        $accessToken = $this->tokenString(
            $connection->access_token,
        );

        if (
            $accessToken !== null
            && ! $this->shouldRefresh($connection)
        ) {
            return $accessToken;
        }

        $refreshToken = $this->tokenString(
            $connection->refresh_token,
        );

        if ($refreshToken === null) {
            throw new CloudflareApiException(
                CloudflareApiException::REFRESH_FAILED,
                'Cloudflare access token cannot be refreshed.',
            );
        }

        try {
            $tokens = $this->oauth->refresh(
                refreshToken: $refreshToken,
                fallbackScopes: is_array($connection->scopes)
                    ? $connection->scopes
                    : [],
            );
        } catch (Throwable) {
            throw new CloudflareApiException(
                CloudflareApiException::REFRESH_FAILED,
                'Cloudflare access token refresh failed.',
            );
        }

        $connection->forceFill([
            'access_token' => $tokens->accessToken,
            'refresh_token' => $tokens->refreshToken
                ?? $refreshToken,
            'scopes' => $tokens->scopes,
            'access_token_expires_at' => $tokens->expiresIn === null
                ? null
                : now()->addSeconds($tokens->expiresIn),
        ])->save();

        return $tokens->accessToken;
    }

    private function shouldRefresh(
        IntegrationConnection $connection,
    ): bool {
        if ($connection->access_token_expires_at === null) {
            return false;
        }

        return $connection->access_token_expires_at
            ->lessThanOrEqualTo(
                now()->addSeconds(self::REFRESH_SKEW_SECONDS),
            );
    }

    private function tokenString(
        #[SensitiveParameter]
        mixed $value,
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
