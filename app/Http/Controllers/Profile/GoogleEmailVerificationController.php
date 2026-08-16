<?php

declare(strict_types=1);

namespace App\Http\Controllers\Profile;

use App\Application\User\Actions\VerifyGoogleEmailAction;
use App\Infrastructure\Identity\GoogleOpenIdClient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

final class GoogleEmailVerificationController
{
    private const SESSION_KEY = 'profile.google_email_verification';

    private const ATTEMPT_TTL_SECONDS = 600;

    public function redirect(
        Request $request,
        GoogleOpenIdClient $google,
    ): RedirectResponse {
        $user = $this->user($request);

        if ($user->email_verified_at !== null) {
            return to_route('panel.profile')
                ->with(
                    'profile_status',
                    'ایمیل شما قبلاً تأیید شده است.',
                );
        }

        if (! $google->configured()) {
            return to_route('panel.profile')
                ->with(
                    'profile_error',
                    'تأیید ایمیل با Google هنوز پیکربندی نشده است.',
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
                'expected_email' => $this->normalizeEmail(
                    $user->email,
                ),
                'started_at' => now()->timestamp,
            ],
        );

        return redirect()->away(
            $google->authorizationUrl(
                state: $state,
                redirectUri: $this->callbackUrl(),
                codeVerifier: $codeVerifier,
            ),
        );
    }

    public function callback(
        Request $request,
        GoogleOpenIdClient $google,
        VerifyGoogleEmailAction $verifyGoogleEmail,
    ): RedirectResponse {
        $user = $this->user($request);
        $attempt = $request->session()->pull(
            self::SESSION_KEY,
        );

        if (! $this->validAttempt($attempt, $user, $request)) {
            return $this->failure(
                'درخواست تأیید ایمیل معتبر نیست یا منقضی شده است.',
            );
        }

        if ($request->filled('error')) {
            return $this->failure(
                'فرایند تأیید ایمیل در Google لغو شد.',
            );
        }

        $codeVerifier = $attempt['code_verifier'];
        $authorizationCode = $request->query('code');

        if (
            ! is_string($authorizationCode)
            || trim($authorizationCode) === ''
        ) {
            return $this->failure(
                'Google کد تأیید معتبری برنگرداند.',
            );
        }

        try {
            $identity = $google->resolveIdentity(
                authorizationCode: $authorizationCode,
                redirectUri: $this->callbackUrl(),
                codeVerifier: $codeVerifier,
            );

            if (! $identity->emailVerified) {
                return $this->failure(
                    'Google مالکیت این ایمیل را تأیید نکرده است.',
                );
            }

            $verifyGoogleEmail->handle(
                user: $user,
                googleEmail: $identity->email,
                expectedEmail: is_string(
                    $attempt['expected_email'] ?? null,
                )
                    ? $attempt['expected_email']
                    : null,
            );
        } catch (ValidationException $exception) {
            return to_route('panel.profile')
                ->withErrors($exception->errors());
        } catch (Throwable) {
            return $this->failure(
                'ارتباط با Google برای تأیید ایمیل ناموفق بود. دوباره تلاش کنید.',
            );
        }

        return to_route('panel.profile')
            ->with(
                'profile_status',
                'ایمیل با موفقیت از طریق Google تأیید شد.',
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
        $issuer = $request->query('iss');

        if (
            ! is_string($state)
            || $state === ''
            || ! is_string($stateHash)
            || $stateHash === ''
            || ! $this->validCodeVerifier($codeVerifier)
            || ! is_int($startedAt)
            || (string) $userId !== (string) $user->getKey()
            || $issuer !== 'https://accounts.google.com'
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

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== ''
            ? $email
            : null;
    }

    private function callbackUrl(): string
    {
        return route(
            'panel.profile.email.google.callback',
        );
    }

    private function failure(string $message): RedirectResponse
    {
        return to_route('panel.profile')
            ->with(
                'profile_error',
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
