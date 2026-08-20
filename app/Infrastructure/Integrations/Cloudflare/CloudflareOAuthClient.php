<?php

declare(strict_types=1);

namespace App\Infrastructure\Integrations\Cloudflare;

use App\Domain\Integration\Cloudflare\CloudflareScopes;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SensitiveParameter;

final class CloudflareOAuthClient
{
    public function configured(): bool
    {
        return $this->clientId() !== ''
            && $this->clientSecret() !== '';
    }

    public function configuredForRead(): bool
    {
        return CloudflareScopes::missing(
            $this->scopes(),
        ) === [];
    }

    public function configuredForDnsWrite(): bool
    {
        return CloudflareScopes::missing(
            $this->scopes(),
            CloudflareScopes::dnsWrite(),
        ) === [];
    }

    public function authorizationUrl(
        string $state,
        string $redirectUri,
        string $codeVerifier,
    ): string {
        $this->ensureConfigured();
        $this->ensureValidCodeVerifier($codeVerifier);

        $query = http_build_query(
            [
                'client_id' => $this->clientId(),
                'redirect_uri' => $redirectUri,
                'response_type' => 'code',
                'scope' => implode(' ', $this->scopes()),
                'state' => $state,
                'code_challenge' => $this->codeChallenge(
                    $codeVerifier,
                ),
                'code_challenge_method' => 'S256',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return $this->authorizationEndpoint().'?'.$query;
    }

    public function exchange(
        string $authorizationCode,
        string $redirectUri,
        string $codeVerifier,
    ): CloudflareOAuthTokenSet {
        $this->ensureConfigured();
        $this->ensureValidCodeVerifier($codeVerifier);

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->post(
                $this->tokenEndpoint(),
                [
                    'grant_type' => 'authorization_code',
                    'code' => $authorizationCode,
                    'redirect_uri' => $redirectUri,
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'code_verifier' => $codeVerifier,
                ],
            );

        $this->ensureSuccessful(
            $response,
            'Cloudflare token exchange failed.',
        );

        return $this->tokenSet(
            response: $response,
            fallbackScopes: $this->scopes(),
        );
    }

    /** @param array<int, mixed> $fallbackScopes */
    public function refresh(
        #[SensitiveParameter]
        string $refreshToken,
        array $fallbackScopes = [],
    ): CloudflareOAuthTokenSet {
        $this->ensureConfigured();

        if (trim($refreshToken) === '') {
            throw new RuntimeException(
                'Cloudflare refresh token is missing.',
            );
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->post(
                $this->tokenEndpoint(),
                [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                ],
            );

        $this->ensureSuccessful(
            $response,
            'Cloudflare token refresh failed.',
        );

        return $this->tokenSet(
            response: $response,
            fallbackScopes: $fallbackScopes,
        );
    }

    public function revoke(
        #[SensitiveParameter]
        string $token,
    ): void {
        $this->ensureConfigured();

        if (trim($token) === '') {
            return;
        }

        $response = Http::asForm()
            ->acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->post(
                $this->revokeEndpoint(),
                [
                    'token' => $token,
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                ],
            );

        $this->ensureSuccessful(
            $response,
            'Cloudflare token revocation failed.',
        );
    }

    /** @return list<string> */
    public function scopes(): array
    {
        $configured = config(
            'services.cloudflare_oauth.scopes',
            CloudflareScopes::oauth(),
        );

        if (! is_array($configured)) {
            return CloudflareScopes::oauth();
        }

        $scopes = $this->normalizeScopes($configured);

        return $scopes === []
            ? CloudflareScopes::oauth()
            : $scopes;
    }

    /**
     * @param  array<int, mixed>  $fallbackScopes
     */
    private function tokenSet(
        Response $response,
        array $fallbackScopes,
    ): CloudflareOAuthTokenSet {
        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || trim($accessToken) === '') {
            throw new RuntimeException(
                'Cloudflare token response did not contain an access token.',
            );
        }

        $refreshToken = $response->json('refresh_token');

        if (! is_string($refreshToken) || trim($refreshToken) === '') {
            $refreshToken = null;
        }

        $expiresIn = $response->json('expires_in');

        if (! is_int($expiresIn) && ! is_numeric($expiresIn)) {
            $expiresIn = null;
        }

        return new CloudflareOAuthTokenSet(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            scopes: $this->responseScopes(
                value: $response->json('scope'),
                fallbackScopes: $fallbackScopes,
            ),
            expiresIn: $expiresIn === null
                ? null
                : max(1, (int) $expiresIn),
        );
    }

    /**
     * @param  array<int, mixed>  $fallbackScopes
     * @return list<string>
     */
    private function responseScopes(
        mixed $value,
        array $fallbackScopes,
    ): array {
        if (is_string($value) && trim($value) !== '') {
            $scopes = preg_split(
                '/\s+/',
                trim($value),
                -1,
                PREG_SPLIT_NO_EMPTY,
            );

            if (is_array($scopes) && $scopes !== []) {
                return array_values(array_unique($scopes));
            }
        }

        $fallbackScopes = $this->normalizeScopes(
            $fallbackScopes,
        );

        return $fallbackScopes === []
            ? $this->scopes()
            : $fallbackScopes;
    }

    /**
     * @param  array<int, mixed>  $scopes
     * @return list<string>
     */
    private function normalizeScopes(array $scopes): array
    {
        $normalized = [];

        foreach ($scopes as $scope) {
            if (! is_string($scope)) {
                continue;
            }

            $scope = trim($scope);

            if ($scope !== '') {
                $normalized[] = $scope;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function codeChallenge(string $codeVerifier): string
    {
        return rtrim(
            strtr(
                base64_encode(
                    hash(
                        'sha256',
                        $codeVerifier,
                        true,
                    ),
                ),
                '+/',
                '-_',
            ),
            '=',
        );
    }

    private function ensureValidCodeVerifier(string $codeVerifier): void
    {
        $length = strlen($codeVerifier);

        if (
            $length < 43
            || $length > 128
            || preg_match(
                '/\A[A-Za-z0-9\-._~]+\z/D',
                $codeVerifier,
            ) !== 1
        ) {
            throw new RuntimeException(
                'Cloudflare PKCE code verifier is invalid.',
            );
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'Cloudflare OAuth is not configured.',
            );
        }
    }

    private function ensureSuccessful(
        Response $response,
        string $message,
    ): void {
        if (! $response->successful()) {
            throw new RuntimeException($message);
        }
    }

    private function clientId(): string
    {
        return trim((string) config(
            'services.cloudflare_oauth.client_id',
            '',
        ));
    }

    private function clientSecret(): string
    {
        return trim((string) config(
            'services.cloudflare_oauth.client_secret',
            '',
        ));
    }

    private function authorizationEndpoint(): string
    {
        return (string) config(
            'services.cloudflare_oauth.authorization_endpoint',
            'https://dash.cloudflare.com/oauth2/auth',
        );
    }

    private function tokenEndpoint(): string
    {
        return (string) config(
            'services.cloudflare_oauth.token_endpoint',
            'https://dash.cloudflare.com/oauth2/token',
        );
    }

    private function revokeEndpoint(): string
    {
        return (string) config(
            'services.cloudflare_oauth.revoke_endpoint',
            'https://dash.cloudflare.com/oauth2/revoke',
        );
    }

    private function connectTimeout(): int
    {
        return max(
            1,
            (int) config(
                'services.cloudflare_oauth.connect_timeout',
                5,
            ),
        );
    }

    private function timeout(): int
    {
        return max(
            1,
            (int) config(
                'services.cloudflare_oauth.timeout',
                10,
            ),
        );
    }
}
