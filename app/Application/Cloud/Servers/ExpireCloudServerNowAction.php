<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Models\Server;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class ExpireCloudServerNowAction
{
    public function execute(
        User $admin,
        int $serverId,
    ): bool {
        if (! $admin->isAdmin()) {
            throw new AuthorizationException();
        }

        $audit = DB::transaction(
            function () use (
                $serverId,
            ): ?array {
                /** @var Server $server */
                $server = Server::query()
                    ->whereKey($serverId)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $server->isCloudProvisioned()) {
                    throw new LogicException(
                        'Only cloud-provisioned servers can be expired manually.',
                    );
                }

                if (
                    $server->isTerminated()
                    || $server->termination_started_at !== null
                ) {
                    throw new LogicException(
                        'Cloud server termination has already started.',
                    );
                }

                if ($server->hasExpired()) {
                    return null;
                }

                $previousExpiresAt = $server->expires_at?->toIso8601String();
                $expiredAt = now();

                $server->forceFill([
                    'expires_at' => $expiredAt,
                ])->saveOrFail();

                return [
                    'provider' => $server->cloud_provider?->value,
                    'previous_expires_at' => $previousExpiresAt,
                    'expired_at' => $expiredAt->toIso8601String(),
                ];
            },
        );

        if ($audit === null) {
            return false;
        }

        logger()->notice(
            'admin.cloud_server.expired_manually',
            [
                'admin_id' => (int) $admin->getKey(),
                'server_id' => $serverId,
                ...$audit,
            ],
        );

        return true;
    }
}
