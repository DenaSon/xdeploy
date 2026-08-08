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

    /**
     * This job never creates a provider resource. A single automatic
     * attempt is sufficient for MVP; readiness can be checked again later
     * without any risk of duplicate billing.
     */
    public int $tries = 1;

    public int $timeout = 180;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 300;

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
            /*
             * SSH unreachability is a connectivity/readiness condition,
             * not a commercial fulfillment failure. In particular, public
             * IP filtering can make a valid provider VPS unreachable from
             * the xDeploy host. Keep the Server inactive and finish this Job
             * successfully so the Order remains fulfilled.
             */
            logger()->warning(
                'server.readiness.ssh_unavailable',
                [
                    'server_id' => $server->getKey(),
                    'cloud_provider' => $server->cloud_provider,
                    'cloud_server_id' => $server->cloud_server_id,
                    'host' => $server->host,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }
}
