<?php

declare(strict_types=1);

namespace App\Application\Server;

use App\Application\Server\Actions\ConnectServerAction;
use App\Application\Server\Actions\EnsureServerOperationReadinessAction;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Models\Server;
use Closure;

final readonly class ServerReadExecutor
{
    public function __construct(
        private ConnectServerAction $connectServer,
        private EnsureServerOperationReadinessAction $readiness,
        private SSHConnectionInterface $ssh,
    ) {}

    /**
     * Execute one read-only server operation inside a ready SSH session.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $read
     * @return TResult
     */
    public function execute(
        Server $server,
        Closure $read,
    ): mixed {
        try {
            $this->connectServer
                ->handle($server);

            $this->readiness
                ->handle();

            return $read();
        } finally {
            $this->ssh
                ->disconnect();
        }
    }
}
