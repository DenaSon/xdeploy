<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Application\Cloud\Events\CloudServerExpired;
use App\Application\Cloud\Events\CloudServerTerminated;
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
        $prepared = $this->prepareTermination(
            $serverId,
        );

        if ($prepared === null) {
            return false;
        }

        $server =
            $prepared['server'];

        $firstAttempt =
            $prepared['first_attempt'];

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

        $expiresAt =
            $server->expires_at;

        if ($expiresAt === null) {
            throw new LogicException(
                sprintf(
                    'Expired Cloud Server [%d] has no expires_at timestamp.',
                    $server->getKey(),
                ),
            );
        }

        $serverName =
            $this->serverDisplayName(
                $server,
            );

        if ($firstAttempt) {
            $this->dispatchLifecycleEvent(
                new CloudServerExpired(
                    userId: (int) $user->getKey(),

                    serverId: (int) $server->getKey(),

                    serverName: $serverName,

                    expiresAt: $expiresAt
                        ->toIso8601String(),
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

        /*
         * The Server has now been soft-deleted by DeleteCloudServerAction,
         * so read the authoritative termination timestamp through
         * withTrashed() before emitting the final lifecycle event.
         */
        $terminatedServer =
            Server::withTrashed()
                ->whereKey(
                    $server->getKey(),
                )
                ->first();

        $terminatedAt =
            $terminatedServer
                ?->terminated_at
                ?->toIso8601String()
            ?? now()->toIso8601String();

        $this->dispatchLifecycleEvent(
            new CloudServerTerminated(
                userId: (int) $user->getKey(),

                serverId: (int) $server->getKey(),

                serverName: $serverName,

                expiresAt: $expiresAt
                    ->toIso8601String(),

                terminatedAt: $terminatedAt,
            ),
        );

        return true;
    }

    /**
     * @return array{
     *     server: Server,
     *     first_attempt: bool
     * }|null
     */
    private function prepareTermination(
        int $serverId,
    ): ?array {
        return DB::transaction(
            function () use (
                $serverId,
            ): ?array {
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

                $firstAttempt =
                    $server->termination_started_at
                    === null;

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

                return [
                    'server' => $server->refresh(),

                    'first_attempt' => $firstAttempt,
                ];
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

    private function dispatchLifecycleEvent(
        object $event,
    ): void {
        try {
            event(
                $event,
            );
        } catch (Throwable $exception) {
            /*
             * Notification/event delivery must never convert a successful
             * Cloud lifecycle side effect into a failed termination.
             */
            report(
                $exception,
            );

            logger()->warning(
                'cloud_server.lifecycle_event_dispatch_failed',
                [
                    'event' => $event::class,

                    'message' => $exception->getMessage(),
                ],
            );
        }
    }
}
