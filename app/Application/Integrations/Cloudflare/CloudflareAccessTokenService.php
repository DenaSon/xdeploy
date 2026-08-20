<?php

declare(strict_types=1);

namespace App\Application\Integrations\Cloudflare;

use App\Domain\Integration\Cloudflare\CloudflareScopes;
use App\Infrastructure\Integrations\Cloudflare\CloudflareApiException;
use App\Infrastructure\Integrations\Cloudflare\CloudflareOAuthClient;
use App\Models\IntegrationConnection;
use SensitiveParameter;
use Throwable;

final readonly class CloudflareAccessTokenService
{
    private const REFRESH_SKEW_SECONDS = 60;

    public function __construct(
        private CloudflareOAuthClient $oauth,
    ) {}

    /**
     * @param  list<string>  $requiredScopes
     */
    public function token(
        IntegrationConnection $connection,
        array $requiredScopes,
    ): string {
        $this->ensureScopes(
            $connection,
            $requiredScopes,
        );

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

        $this->ensureScopes(
            $connection->fresh() ?? $connection,
            $requiredScopes,
        );

        return $tokens->accessToken;
    }

    /**
     * @param  list<string>  $requiredScopes
     */
    public function hasScopes(
        IntegrationConnection $connection,
        array $requiredScopes,
    ): bool {
        $scopes = is_array($connection->scopes)
            ? $connection->scopes
            : [];

        return CloudflareScopes::missing(
            $scopes,
            $requiredScopes,
        ) === [];
    }

    /**
     * @param  list<string>  $requiredScopes
     */
    private function ensureScopes(
        IntegrationConnection $connection,
        array $requiredScopes,
    ): void {
        if ($this->hasScopes($connection, $requiredScopes)) {
            return;
        }

        throw new CloudflareApiException(
            CloudflareApiException::MISSING_SCOPES,
            'Cloudflare connection is missing required scopes.',
        );
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
