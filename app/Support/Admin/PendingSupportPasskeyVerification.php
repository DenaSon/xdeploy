<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Server;
use App\Models\User;

final class PendingSupportPasskeyVerification
{
    public const string SESSION_KEY = 'admin.support_passkey_verification';

    private const int WINDOW_SECONDS = 120;

    public function prepare(
        User $admin,
        Server $server,
        string $reason,
    ): void {
        session()->put(
            self::SESSION_KEY,
            [
                'admin_user_id' => (int) $admin->getKey(),
                'server_id' => (int) $server->getKey(),
                'reason' => trim($reason),
                'prepared_at' => now()->timestamp,
                'options' => null,
            ],
        );
    }

    public function attachOptions(
        User $admin,
        Server $server,
        string $serializedOptions,
    ): bool {
        $state = $this->validState(
            admin: $admin,
            server: $server,
        );

        if ($state === null) {
            $this->revoke();

            return false;
        }

        $state['options'] = $serializedOptions;

        session()->put(
            self::SESSION_KEY,
            $state,
        );

        return true;
    }

    /**
     * @return array{reason: string, options: string}|null
     */
    public function consume(
        User $admin,
        Server $server,
    ): ?array {
        $state = $this->validState(
            admin: $admin,
            server: $server,
        );

        $this->revoke();

        if ($state === null) {
            return null;
        }

        $reason = $state['reason'] ?? null;
        $options = $state['options'] ?? null;

        if (
            ! is_string($reason)
            || trim($reason) === ''
            || ! is_string($options)
            || $options === ''
        ) {
            return null;
        }

        return [
            'reason' => $reason,
            'options' => $options,
        ];
    }

    public function isPrepared(
        User $admin,
        Server $server,
    ): bool {
        return $this->validState(
            admin: $admin,
            server: $server,
        ) !== null;
    }

    public function revoke(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validState(
        User $admin,
        Server $server,
    ): ?array {
        $state = session()->get(self::SESSION_KEY);

        if (! is_array($state)) {
            return null;
        }

        $adminUserId = $state['admin_user_id'] ?? null;
        $serverId = $state['server_id'] ?? null;
        $preparedAt = $state['prepared_at'] ?? null;

        if (
            ! is_int($adminUserId)
            || ! is_int($serverId)
            || ! is_int($preparedAt)
        ) {
            return null;
        }

        if (
            $adminUserId !== (int) $admin->getKey()
            || $serverId !== (int) $server->getKey()
        ) {
            return null;
        }

        if ((now()->timestamp - $preparedAt) > self::WINDOW_SECONDS) {
            return null;
        }

        return $state;
    }
}
