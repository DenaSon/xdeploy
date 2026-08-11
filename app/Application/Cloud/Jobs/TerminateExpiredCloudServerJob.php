<?php

declare(strict_types=1);

namespace App\Application\Cloud\Jobs;

use App\Application\Cloud\Events\CloudServerTerminationFailed;
use App\Application\Cloud\Servers\TerminateExpiredCloudServerAction;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class TerminateExpiredCloudServerJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 7200;

    public function __construct(
        public readonly int $serverId,
    ) {
        $this->onQueue(
            'provisioning',
        );
    }

    public function uniqueId(): string
    {
        return sprintf(
            'cloud-server:%d:termination',
            $this->serverId,
        );
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            60,
            300,
            900,
            1800,
        ];
    }

    public function handle(
        TerminateExpiredCloudServerAction $action,
    ): void {
        $action->execute(
            $this->serverId,
        );
    }

    public function failed(
        ?Throwable $exception,
    ): void {
        logger()->error(
            'cloud_server.expiration_termination_failed',
            [
                'server_id' => $this->serverId,
                'exception' => $exception !== null
                    ? $exception::class
                    : null,
                'message' => $exception?->getMessage(),
            ],
        );

        /** @var Server|null $server */
        $server = Server::query()
            ->whereKey(
                $this->serverId,
            )
            ->first();

        if (
            ! $server instanceof Server
            || $server->expires_at === null
        ) {
            return;
        }

        $message = trim(
            (string) (
                $exception?->getMessage()
                ?? $server->termination_last_error
                ?? 'Cloud Server termination failed.'
            ),
        );

        if ($message === '') {
            $message =
                'Cloud Server termination failed.';
        }

        try {
            CloudServerTerminationFailed::dispatch(
                userId: (int) $server->user_id,

                serverId: (int) $server->getKey(),

                serverName: $this->serverDisplayName(
                    $server,
                ),

                expiresAt: $server->expires_at
                    ->toIso8601String(),

                attempts: max(
                    1,
                    $server->termination_attempts,
                ),

                message: $message,
            );
        } catch (Throwable $eventException) {
            report(
                $eventException,
            );

            logger()->warning(
                'cloud_server.termination_failed_event_dispatch_failed',
                [
                    'server_id' => $this->serverId,

                    'message' => $eventException->getMessage(),
                ],
            );
        }
    }

    private function serverDisplayName(
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
