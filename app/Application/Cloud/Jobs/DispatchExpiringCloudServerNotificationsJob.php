<?php

declare(strict_types=1);

namespace App\Application\Cloud\Jobs;

use App\Application\Cloud\Events\CloudServerExpiringSoon;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchExpiringCloudServerNotificationsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 1200;

    public function __construct()
    {
        $this->onQueue(
            'provisioning',
        );
    }

    public function uniqueId(): string
    {
        return 'cloud-server-expiring-notification-scan';
    }

    public function handle(): void
    {
        $now = now();

        $until = $now
            ->copy()
            ->addHours(24);

        Server::query()
            ->whereNotNull(
                'cloud_provider',
            )
            ->whereNotNull(
                'cloud_server_id',
            )
            ->whereNotNull(
                'expires_at',
            )
            ->where(
                'expires_at',
                '>',
                $now,
            )
            ->where(
                'expires_at',
                '<=',
                $until,
            )
            ->select([
                'id',
                'user_id',
                'name',
                'host',
                'expires_at',
            ])
            ->orderBy('id')
            ->chunkById(
                100,
                static function ($servers): void {
                    foreach ($servers as $server) {
                        $expiresAt =
                            $server->expires_at;

                        if ($expiresAt === null) {
                            continue;
                        }

                        CloudServerExpiringSoon::dispatch(
                            userId: (int) $server->user_id,

                            serverId: (int) $server->getKey(),

                            serverName: self::serverDisplayName(
                                $server,
                            ),

                            expiresAt: $expiresAt
                                ->toIso8601String(),
                        );
                    }
                },
            );
    }

    private static function serverDisplayName(
        Server $server,
    ): string {
        $name = trim(
            (string) $server->name,
        );

        if ($name !== '') {
            return $name;
        }

        $host = trim(
            (string) $server->host,
        );

        if ($host !== '') {
            return $host;
        }

        return sprintf(
            'VPS #%d',
            (int) $server->getKey(),
        );
    }
}
