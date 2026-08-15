<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Server;
use App\Models\User;

final class AdminSupportAccessSession
{
    public const string SESSION_KEY = 'admin.support_access';

    private const int WINDOW_SECONDS = 300;

    public function grant(
        User $admin,
        Server $server,
    ): void {
        session()->put(
            self::SESSION_KEY,
            [
                'admin_user_id' => (int) $admin->getKey(),
                'server_id' => (int) $server->getKey(),
                'confirmed_at' => now()->timestamp,
            ],
        );
    }

    public function isGranted(
        User $admin,
        Server $server,
    ): bool {
        $state = session()->get(self::SESSION_KEY);

        if (! is_array($state)) {
            return false;
        }

        $adminUserId = $state['admin_user_id'] ?? null;
        $serverId = $state['server_id'] ?? null;
        $confirmedAt = $state['confirmed_at'] ?? null;

        if (
            ! is_int($adminUserId)
            || ! is_int($serverId)
            || ! is_int($confirmedAt)
        ) {
            $this->revoke();

            return false;
        }

        if (
            $adminUserId !== (int) $admin->getKey()
            || $serverId !== (int) $server->getKey()
        ) {
            return false;
        }

        if ((now()->timestamp - $confirmedAt) > self::WINDOW_SECONDS) {
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
