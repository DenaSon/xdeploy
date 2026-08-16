<?php

declare(strict_types=1);

namespace App\Application\Billing\Jobs;

use App\Application\Cloud\Actions\VerifyCloudServerSshReadinessAction;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class VerifyProvisionedServerReadinessJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const array RETRY_DELAYS_SECONDS = [
        60,
        120,
        180,
    ];

    /**
     * Readiness retries never create or bill a second provider resource.
     * SSH password rotation may mutate the delivered server, but it persists
     * a recoverable candidate credential before the remote change and each
     * retry reconciles that pending state before doing any further mutation.
     */
    public int $tries = 4;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 900;

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
            'server-readiness:%d',
            $this->serverId,
        );
    }

    public function handle(
        VerifyCloudServerSshReadinessAction $readiness,
    ): void {
        /** @var Server $server */
        $server = Server::query()
            ->findOrFail(
                $this->serverId,
            );

        if ($server->isActive()) {
            return;
        }

        try {
            $readiness->handle(
                $server,
            );
        } catch (CloudServerSshUnavailableException $exception) {
            $attempt = $this->attempts();

            logger()->warning(
                'server.readiness.ssh_unavailable',
                [
                    'server_id' => $server->getKey(),
                    'cloud_provider' => $server->cloud_provider,
                    'cloud_server_id' => $server->cloud_server_id,
                    'host' => $server->host,
                    'attempt' => $attempt,
                    'max_attempts' => $this->tries,
                    'message' => $exception->getMessage(),
                ],
            );

            if ($attempt >= $this->tries) {
                logger()->warning(
                    'server.readiness.retries_exhausted',
                    [
                        'server_id' => $server->getKey(),
                        'cloud_provider' => $server->cloud_provider,
                        'cloud_server_id' => $server->cloud_server_id,
                        'attempts' => $attempt,
                    ],
                );

                return;
            }

            $delay = self::RETRY_DELAYS_SECONDS[
                min(
                    $attempt - 1,
                    count(self::RETRY_DELAYS_SECONDS) - 1,
                )
            ];

            logger()->info(
                'server.readiness.retry_scheduled',
                [
                    'server_id' => $server->getKey(),
                    'attempt' => $attempt,
                    'delay_seconds' => $delay,
                ],
            );

            $this->release(
                $delay,
            );
        }
    }
}
