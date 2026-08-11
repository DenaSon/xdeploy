<?php

declare(strict_types=1);

namespace App\Application\Cloud\Jobs;

use App\Application\Cloud\Servers\TerminateExpiredCloudServerAction;
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
    }
}
