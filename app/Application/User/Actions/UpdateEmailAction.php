<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UpdateEmailAction
{
    public function handle(
        User $user,
        ?string $email,
    ): void {
        $email = $this->normalize($email);
        $currentEmail = $this->normalize($user->email);

        if ($currentEmail === $email) {
            if ($user->email !== $email) {
                $user->forceFill([
                    'email' => $email,
                ])->save();
            }

            return;
        }

        if (
            $email !== null
            && $this->belongsToAnotherUser($user, $email)
        ) {
            throw ValidationException::withMessages([
                'email' => 'این ایمیل قبلاً برای حساب دیگری ثبت شده است.',
            ]);
        }

        $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();
    }

    private function normalize(?string $email): ?string
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
