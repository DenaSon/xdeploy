<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\User;

final class AdminImpersonationSession
{
    public const string SESSION_KEY = 'admin.impersonation';

    public function start(
        User $admin,
        User $target,
    ): void {
        session()->put(
            self::SESSION_KEY,
            [
                'admin_user_id' => (int) $admin->getKey(),
                'target_user_id' => (int) $target->getKey(),
            ],
        );
    }

    public function isActiveFor(User $user): bool
    {
        $state = $this->state();

        return $state !== null
            && $state['target_user_id'] === (int) $user->getKey();
    }

    public function adminIdFor(User $user): ?int
    {
        $state = $this->state();

        if (
            $state === null
            || $state['target_user_id'] !== (int) $user->getKey()
        ) {
            return null;
        }

        return $state['admin_user_id'];
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array{admin_user_id: int, target_user_id: int}|null
     */
    private function state(): ?array
    {
        $state = session()->get(self::SESSION_KEY);

        if (! is_array($state)) {
            return null;
        }

        $adminUserId = $state['admin_user_id'] ?? null;
        $targetUserId = $state['target_user_id'] ?? null;

        if (! is_int($adminUserId) || ! is_int($targetUserId)) {
            $this->clear();

            return null;
        }

        return [
            'admin_user_id' => $adminUserId,
            'target_user_id' => $targetUserId,
        ];
    }
}
