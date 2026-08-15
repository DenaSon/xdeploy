<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;

final class AdminPasskeyVerificationSession
{
    public const string SESSION_KEY = 'admin.passkey_verification';

    private const int WINDOW_SECONDS = 3_600;

    public function grant(User $admin): void
    {
        session()->put(
            self::SESSION_KEY,
            [
                'admin_user_id' => (int) $admin->getKey(),
                'verified_at' => now()->timestamp,
            ],
        );
    }

    public function isGranted(User $admin): bool
    {
        $state = session()->get(self::SESSION_KEY);

        if (! is_array($state)) {
            return false;
        }

        $adminUserId = $state['admin_user_id'] ?? null;
        $verifiedAt = $state['verified_at'] ?? null;

        if (! is_int($adminUserId) || ! is_int($verifiedAt)) {
            $this->revoke();

            return false;
        }

        if ($adminUserId !== (int) $admin->getKey()) {
            $this->revoke();

            return false;
        }

        if ((now()->timestamp - $verifiedAt) > self::WINDOW_SECONDS) {
            $this->revoke();

            return false;
        }

        return true;
    }

    public function revoke(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
