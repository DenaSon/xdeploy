<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final readonly class TerminateExpiredCloudServerAction
{
    public function __construct(
        private DeleteCloudServerAction $deleteCloudServer,
    ) {}

    /**
     * Returns true when an expired Cloud Server was selected for
     * termination. Returns false for a no-op / non-eligible Server.
     */
    public function execute(
        int $serverId,
    ): bool {
        $server = $this->prepareTermination(
            $serverId,
        );

        if (! $server instanceof Server) {
            return false;
        }

        $user = $server->user()
            ->first();

        if (! $user instanceof User) {
            throw new LogicException(
                sprintf(
                    'Cloud Server [%d] has no valid owner.',
                    $server->getKey(),
                ),
            );
        }

        try {
            /*
             * Never keep a DB transaction open while calling the
             * external Cloud Provider.
             */
            $this->deleteCloudServer->handle(
                user: $user,
                serverId: (int) $server->getKey(),
            );
        } catch (Throwable $exception) {
            $this->recordFailure(
                serverId: (int) $server->getKey(),
                exception: $exception,
            );

            throw $exception;
        }

        return true;
    }

    private function prepareTermination(
        int $serverId,
    ): ?Server {
        return DB::transaction(
            function () use (
                $serverId,
            ): ?Server {
                /** @var Server|null $server */
                $server = Server::query()
                    ->whereKey(
                        $serverId,
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $server instanceof Server) {
                    /*
                     * Already soft-deleted, missing, or otherwise no
                     * longer eligible for this lifecycle workflow.
                     */
                    return null;
                }

                if (
                    ! $server->isCloudProvisioned()
                    || ! $server->hasExpired()
                ) {
                    return null;
                }

                $server->forceFill([
                    /*
                     * Expired resources must immediately stop appearing
                     * as operationally available inside xDeploy.
                     */
                    'status' => ServerStatus::Inactive,

                    'termination_started_at' => $server->termination_started_at
                        ?? now(),

                    'termination_last_attempt_at' => now(),

                    'termination_attempts' => $server->termination_attempts + 1,

                    'termination_last_error' => null,
                ])->saveOrFail();

                return $server->refresh();
            },
        );
    }

    private function recordFailure(
        int $serverId,
        Throwable $exception,
    ): void {
        DB::transaction(
            function () use (
                $serverId,
                $exception,
            ): void {
                /** @var Server|null $server */
                $server = Server::query()
                    ->whereKey(
                        $serverId,
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $server instanceof Server) {
                    return;
                }

                $message = trim(
                    $exception->getMessage(),
                );

                if ($message === '') {
                    $message =
                        $exception::class;
                }

                $server->forceFill([
                    'termination_last_error' => mb_substr(
                        $message,
                        0,
                        4000,
                    ),
                ])->saveOrFail();
            },
        );
    }
}
