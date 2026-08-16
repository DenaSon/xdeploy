<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class VerifyGoogleEmailAction
{
    public function handle(
        User $user,
        string $googleEmail,
        ?string $expectedEmail,
    ): void {
        $googleEmail = $this->normalizeRequired($googleEmail);
        $expectedEmail = $this->normalizeOptional($expectedEmail);
        $currentEmail = $this->normalizeOptional($user->email);

        if ($expectedEmail !== null) {
            if ($currentEmail !== $expectedEmail) {
                throw ValidationException::withMessages([
                    'email' => 'ایمیل حساب در طول فرایند تأیید تغییر کرده است. دوباره تلاش کنید.',
                ]);
            }

            if (! hash_equals($expectedEmail, $googleEmail)) {
                throw ValidationException::withMessages([
                    'email' => 'حساب Google انتخاب‌شده با ایمیل ثبت‌شده مطابقت ندارد.',
                ]);
            }

            $user->forceFill([
                'email_verified_at' => now(),
            ])->save();

            return;
        }

        if ($currentEmail !== null) {
            throw ValidationException::withMessages([
                'email' => 'ایمیل حساب در طول فرایند تأیید تغییر کرده است. دوباره تلاش کنید.',
            ]);
        }

        if ($this->belongsToAnotherUser($user, $googleEmail)) {
            throw ValidationException::withMessages([
                'email' => 'ایمیل Google انتخاب‌شده برای حساب دیگری ثبت شده است.',
            ]);
        }

        $user->forceFill([
            'email' => $googleEmail,
            'email_verified_at' => now(),
        ])->save();
    }

    private function normalizeRequired(string $email): string
    {
        $email = mb_strtolower(trim($email));

        if (
            $email === ''
            || mb_strlen($email) > 254
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw ValidationException::withMessages([
                'email' => 'Google یک ایمیل معتبر برنگرداند.',
            ]);
        }

        return $email;
    }

    private function normalizeOptional(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email !== ''
            ? $email
            : null;
    }

    private function belongsToAnotherUser(
        User $user,
        string $email,
    ): bool {
        return User::query()
            ->where(
                $user->getKeyName(),
                '!=',
                $user->getKey(),
            )
            ->whereRaw(
                'LOWER(email) = ?',
                [$email],
            )
            ->exists();
    }
}
