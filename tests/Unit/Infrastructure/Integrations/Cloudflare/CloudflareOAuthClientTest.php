<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Integrations\Cloudflare;

use App\Infrastructure\Integrations\Cloudflare\CloudflareOAuthClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class CloudflareOAuthClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.cloudflare_oauth.client_id' => 'cloudflare-client-id',
            'services.cloudflare_oauth.client_secret' => 'cloudflare-client-secret',
            'services.cloudflare_oauth.authorization_endpoint' => 'https://dash.cloudflare.com/oauth2/auth',
            'services.cloudflare_oauth.token_endpoint' => 'https://dash.cloudflare.com/oauth2/token',
            'services.cloudflare_oauth.revoke_endpoint' => 'https://dash.cloudflare.com/oauth2/revoke',
            'services.cloudflare_oauth.scopes' => [
                'account.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            'services.cloudflare_oauth.connect_timeout' => 5,
            'services.cloudflare_oauth.timeout' => 10,
        ]);
    }

    public function test_authorization_url_uses_state_pkce_and_configured_scopes(): void
    {
        $verifier = str_repeat('a', 64);

        $url = app(CloudflareOAuthClient::class)
            ->authorizationUrl(
                state: 'state-value',
                redirectUri: 'https://coreflare.example/callback',
                codeVerifier: $verifier,
            );

        parse_str(
            (string) parse_url($url, PHP_URL_QUERY),
            $query,
        );

        self::assertSame(
            'https://dash.cloudflare.com/oauth2/auth',
            strtok($url, '?'),
        );
        self::assertSame(
            'cloudflare-client-id',
            $query['client_id'] ?? null,
        );
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame(
            'account.read zone.read dns.read offline_access',
            $query['scope'] ?? null,
        );
        self::assertSame('state-value', $query['state'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
        self::assertSame(
            $this->codeChallenge($verifier),
            $query['code_challenge'] ?? null,
        );
        self::assertArrayNotHasKey('code_verifier', $query);
        self::assertTrue(
            app(CloudflareOAuthClient::class)->configuredForRead(),
        );
    }

    public function test_exchange_returns_normalized_token_set(): void
    {
        Http::fake([
            'https://dash.cloudflare.com/oauth2/token' => Http::response(
                [
                    'access_token' => 'access-token',
                    'refresh_token' => 'refresh-token',
                    'expires_in' => 3600,
                    'scope' => 'account.read zone.read dns.read offline_access',
                    'token_type' => 'Bearer',
                ],
                200,
            ),
        ]);

        $tokens = app(CloudflareOAuthClient::class)
            ->exchange(
                authorizationCode: 'authorization-code',
                redirectUri: 'https://coreflare.example/callback',
                codeVerifier: str_repeat('b', 64),
            );

        self::assertSame('access-token', $tokens->accessToken);
        self::assertSame('refresh-token', $tokens->refreshToken);
        self::assertSame(3600, $tokens->expiresIn);
        self::assertSame(
            [
                'account.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            $tokens->scopes,
        );

        Http::assertSent(
            static fn ($request): bool => $request->url()
                === 'https://dash.cloudflare.com/oauth2/token'
                && $request['grant_type'] === 'authorization_code'
                && $request['client_id'] === 'cloudflare-client-id'
                && $request['client_secret'] === 'cloudflare-client-secret'
                && $request['code'] === 'authorization-code'
                && $request['code_verifier'] === str_repeat('b', 64),
        );
    }

    public function test_refresh_rotates_access_token_and_preserves_granted_scopes_when_omitted(): void
    {
        Http::fake([
            'https://dash.cloudflare.com/oauth2/token' => Http::response(
                [
                    'access_token' => 'new-access-token',
                    'refresh_token' => 'new-refresh-token',
                    'expires_in' => 7200,
                    'token_type' => 'Bearer',
                ],
                200,
            ),
        ]);

        $tokens = app(CloudflareOAuthClient::class)
            ->refresh(
                refreshToken: 'old-refresh-token',
                fallbackScopes: [
                    'account.read',
                    'zone.read',
                    'dns.read',
                    'offline_access',
                ],
            );

        self::assertSame('new-access-token', $tokens->accessToken);
        self::assertSame('new-refresh-token', $tokens->refreshToken);
        self::assertSame(7200, $tokens->expiresIn);
        self::assertSame(
            [
                'account.read',
                'zone.read',
                'dns.read',
                'offline_access',
            ],
            $tokens->scopes,
        );

        Http::assertSent(
            static fn ($request): bool => $request->url()
                === 'https://dash.cloudflare.com/oauth2/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'old-refresh-token'
                && $request['client_id'] === 'cloudflare-client-id'
                && $request['client_secret'] === 'cloudflare-client-secret',
        );
    }

    public function test_exchange_does_not_accept_failed_token_response(): void
    {
        Http::fake([
            'https://dash.cloudflare.com/oauth2/token' => Http::response(
                ['error' => 'invalid_grant'],
                400,
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cloudflare token exchange failed.',
        );

        app(CloudflareOAuthClient::class)
            ->exchange(
                authorizationCode: 'bad-code',
                redirectUri: 'https://coreflare.example/callback',
                codeVerifier: str_repeat('c', 64),
            );
    }

    public function test_revoke_posts_token_without_exposing_it_in_configuration(): void
    {
        Http::fake([
            'https://dash.cloudflare.com/oauth2/revoke' => Http::response(
                [],
                200,
            ),
        ]);

        app(CloudflareOAuthClient::class)
            ->revoke('secret-token');

        Http::assertSent(
            static fn ($request): bool => $request->url()
                === 'https://dash.cloudflare.com/oauth2/revoke'
                && $request['token'] === 'secret-token'
                && $request['client_id'] === 'cloudflare-client-id'
                && $request['client_secret'] === 'cloudflare-client-secret',
        );
    }

    private function codeChallenge(string $verifier): string
    {
        return rtrim(
            strtr(
                base64_encode(
                    hash('sha256', $verifier, true),
                ),
                '+/',
                '-_',
            ),
            '=',
        );
    }
}
