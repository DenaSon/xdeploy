<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class AttachVerifiedGoogleEmailAction
{
    public function handle(
        User $user,
        string $googleEmail,
    ): void {
        if ($user->email_verified_at !== null) {
            throw ValidationException::withMessages([
                'email' => 'ایمیل حساب قبلاً تأیید شده است.',
            ]);
        }

        $googleEmail = $this->normalizeRequired($googleEmail);

        if ($this->belongsToAnotherUser($user, $googleEmail)) {
            throw ValidationException::withMessages([
                'email' => 'این ایمیل Google برای حساب دیگری ثبت شده است.',
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
