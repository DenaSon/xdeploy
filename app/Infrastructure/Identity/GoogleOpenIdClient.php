<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoogleOpenIdClient
{
    public function configured(): bool
    {
        return $this->clientId() !== ''
            && $this->clientSecret() !== '';
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
                'scope' => 'openid email',
                'state' => $state,
                'code_challenge' => $this->codeChallenge(
                    $codeVerifier,
                ),
                'code_challenge_method' => 'S256',
                'access_type' => 'online',
                'prompt' => 'select_account',
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return $this->authorizationEndpoint().'?'.$query;
    }

    public function resolveIdentity(
        string $authorizationCode,
        string $redirectUri,
        string $codeVerifier,
    ): GoogleOpenIdIdentity {
        $this->ensureConfigured();
        $this->ensureValidCodeVerifier($codeVerifier);

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->post(
                $this->tokenEndpoint(),
                [
                    'code' => $authorizationCode,
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'redirect_uri' => $redirectUri,
                    'grant_type' => 'authorization_code',
                    'code_verifier' => $codeVerifier,
                ],
            );

        $this->ensureSuccessful(
            $tokenResponse,
            'Google token exchange failed.',
        );

        $accessToken = $tokenResponse->json('access_token');

        if (! is_string($accessToken) || trim($accessToken) === '') {
            throw new RuntimeException(
                'Google token response did not contain an access token.',
            );
        }

        $identityResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout($this->timeout())
            ->get($this->userInfoEndpoint());

        $this->ensureSuccessful(
            $identityResponse,
            'Google user info request failed.',
        );

        $subject = $identityResponse->json('sub');
        $email = $identityResponse->json('email');
        $emailVerified = $identityResponse->json('email_verified');

        if (
            ! is_string($subject)
            || trim($subject) === ''
            || ! is_string($email)
            || trim($email) === ''
            || ! is_bool($emailVerified)
        ) {
            throw new RuntimeException(
                'Google user info response was incomplete.',
            );
        }

        return new GoogleOpenIdIdentity(
            subject: $subject,
            email: $email,
            emailVerified: $emailVerified,
        );
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
                'Google PKCE code verifier is invalid.',
            );
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'Google OpenID Connect is not configured.',
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
            'services.google_oidc.client_id',
            '',
        ));
    }

    private function clientSecret(): string
    {
        return trim((string) config(
            'services.google_oidc.client_secret',
            '',
        ));
    }

    private function authorizationEndpoint(): string
    {
        return (string) config(
            'services.google_oidc.authorization_endpoint',
            'https://accounts.google.com/o/oauth2/v2/auth',
        );
    }

    private function tokenEndpoint(): string
    {
        return (string) config(
            'services.google_oidc.token_endpoint',
            'https://oauth2.googleapis.com/token',
        );
    }

    private function userInfoEndpoint(): string
    {
        return (string) config(
            'services.google_oidc.userinfo_endpoint',
            'https://openidconnect.googleapis.com/v1/userinfo',
        );
    }

    private function connectTimeout(): int
    {
        return max(
            1,
            (int) config(
                'services.google_oidc.connect_timeout',
                5,
            ),
        );
    }

    private function timeout(): int
    {
        return max(
            1,
            (int) config(
                'services.google_oidc.timeout',
                10,
            ),
        );
    }
}
