<?php

declare(strict_types=1);

namespace App\Http\Controllers\Integrations;

use App\Domain\Integration\Enums\IntegrationProvider;
use App\Infrastructure\Integrations\Cloudflare\CloudflareOAuthClient;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

final class CloudflareConnectionController
{
    private const SESSION_KEY = 'integrations.cloudflare.oauth';

    private const ATTEMPT_TTL_SECONDS = 600;

    public function redirect(
        Request $request,
        CloudflareOAuthClient $cloudflare,
    ): RedirectResponse {
        $user = $this->user($request);

        if (! $cloudflare->configured()) {
            return $this->failure(
                'اتصال Cloudflare هنوز در این محیط پیکربندی نشده است.',
            );
        }

        $state = bin2hex(random_bytes(32));
        $codeVerifier = $this->codeVerifier();

        $request->session()->put(
            self::SESSION_KEY,
            [
                'state_hash' => hash('sha256', $state),
                'code_verifier' => $codeVerifier,
                'user_id' => $user->getKey(),
                'started_at' => now()->timestamp,
            ],
        );

        return redirect()->away(
            $cloudflare->authorizationUrl(
                state: $state,
                redirectUri: $this->callbackUrl(),
                codeVerifier: $codeVerifier,
            ),
        );
    }

    public function callback(
        Request $request,
        CloudflareOAuthClient $cloudflare,
    ): RedirectResponse {
        $user = $this->user($request);
        $attempt = $request->session()->pull(
            self::SESSION_KEY,
        );

        if (! $this->validAttempt($attempt, $user, $request)) {
            return $this->failure(
                'درخواست اتصال Cloudflare معتبر نیست یا منقضی شده است.',
            );
        }

        if ($request->filled('error')) {
            return $this->failure(
                'اتصال Cloudflare لغو شد یا مجوز لازم صادر نشد.',
            );
        }

        $authorizationCode = $request->query('code');

        if (
            ! is_string($authorizationCode)
            || trim($authorizationCode) === ''
        ) {
            return $this->failure(
                'Cloudflare کد معتبر لازم برای تکمیل اتصال را برنگرداند.',
            );
        }

        $codeVerifier = $attempt['code_verifier'];

        try {
            $tokens = $cloudflare->exchange(
                authorizationCode: $authorizationCode,
                redirectUri: $this->callbackUrl(),
                codeVerifier: $codeVerifier,
            );
        } catch (Throwable) {
            return $this->failure(
                'برقراری اتصال با Cloudflare ناموفق بود. دوباره تلاش کنید.',
            );
        }

        IntegrationConnection::query()->updateOrCreate(
            [
                'user_id' => $user->getKey(),
                'provider' => IntegrationProvider::Cloudflare->value,
            ],
            [
                'access_token' => $tokens->accessToken,
                'refresh_token' => $tokens->refreshToken,
                'scopes' => $tokens->scopes,
                'access_token_expires_at' => $tokens->expiresIn === null
                    ? null
                    : now()->addSeconds($tokens->expiresIn),
                'connected_at' => now(),
                'last_synced_at' => null,
            ],
        );

        return to_route('panel.integrations.index')
            ->with(
                'integration_status',
                'Cloudflare با موفقیت به حساب شما متصل شد.',
            );
    }

    public function disconnect(
        Request $request,
        CloudflareOAuthClient $cloudflare,
    ): RedirectResponse {
        $user = $this->user($request);

        $connection = IntegrationConnection::query()
            ->ownedBy($user)
            ->where(
                'provider',
                IntegrationProvider::Cloudflare->value,
            )
            ->first();

        if (! $connection instanceof IntegrationConnection) {
            return to_route('panel.integrations.index');
        }

        $revocationAttempts = 0;
        $successfulRevocations = 0;

        $tokens = array_values(
            array_unique(
                array_filter(
                    [
                        $connection->refresh_token,
                        $connection->access_token,
                    ],
                    static fn (mixed $token): bool => is_string($token)
                        && trim($token) !== '',
                ),
            ),
        );

        foreach ($tokens as $token) {
            $revocationAttempts++;

            try {
                $cloudflare->revoke($token);
                $successfulRevocations++;
            } catch (Throwable) {
                // Local disconnect must remain possible even if Cloudflare is unavailable.
            }
        }

        $connection->delete();

        if (
            $revocationAttempts > 0
            && $successfulRevocations === 0
        ) {
            return $this->failure(
                'اتصال از Coreflare حذف شد، اما لغو دسترسی در Cloudflare تأیید نشد. مجوز Coreflare را در Cloudflare نیز بررسی کنید.',
            );
        }

        return to_route('panel.integrations.index')
            ->with(
                'integration_status',
                'اتصال Cloudflare با موفقیت قطع شد.',
            );
    }

    private function validAttempt(
        mixed $attempt,
        User $user,
        Request $request,
    ): bool {
        if (! is_array($attempt)) {
            return false;
        }

        $state = $request->query('state');
        $stateHash = $attempt['state_hash'] ?? null;
        $codeVerifier = $attempt['code_verifier'] ?? null;
        $userId = $attempt['user_id'] ?? null;
        $startedAt = $attempt['started_at'] ?? null;

        if (
            ! is_string($state)
            || $state === ''
            || ! is_string($stateHash)
            || $stateHash === ''
            || ! $this->validCodeVerifier($codeVerifier)
            || ! is_int($startedAt)
            || (string) $userId !== (string) $user->getKey()
        ) {
            return false;
        }

        if (
            ! hash_equals(
                $stateHash,
                hash('sha256', $state),
            )
        ) {
            return false;
        }

        $age = now()->timestamp - $startedAt;

        return $age >= 0
            && $age <= self::ATTEMPT_TTL_SECONDS;
    }

    private function codeVerifier(): string
    {
        return rtrim(
            strtr(
                base64_encode(
                    random_bytes(64),
                ),
                '+/',
                '-_',
            ),
            '=',
        );
    }

    private function validCodeVerifier(mixed $codeVerifier): bool
    {
        if (! is_string($codeVerifier)) {
            return false;
        }

        $length = strlen($codeVerifier);

        return $length >= 43
            && $length <= 128
            && preg_match(
                '/\A[A-Za-z0-9\-._~]+\z/D',
                $codeVerifier,
            ) === 1;
    }

    private function callbackUrl(): string
    {
        return route(
            'panel.integrations.cloudflare.callback',
        );
    }

    private function failure(string $message): RedirectResponse
    {
        return to_route('panel.integrations.index')
            ->with(
                'integration_error',
                $message,
            );
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
        );

        return $user;
    }
}
