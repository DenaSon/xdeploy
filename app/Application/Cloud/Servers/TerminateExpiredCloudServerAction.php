<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Application\Cloud\Events\CloudServerExpired;
use App\Application\Cloud\Events\CloudServerTerminated;
use App\Application\Cloud\Servers\Termination\CloudServerTerminationDecision;
use App\Application\Cloud\Servers\Termination\CloudServerTerminationPolicyResolver;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
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
        private ?CloudProviderRegistryInterface $providers = null,
        private ?CloudServerTerminationPolicyResolver $terminationPolicies = null,
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

        $server = $prepared['server'];
        $firstAttempt = $prepared['first_attempt'];

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

        $expiresAt = $server->expires_at;

        if ($expiresAt === null) {
            throw new LogicException(
                sprintf(
                    'Expired Cloud Server [%d] has no expires_at timestamp.',
                    $server->getKey(),
                ),
            );
        }

        $serverName = $this->serverDisplayName(
            $server,
        );

        if ($firstAttempt) {
            $this->dispatchLifecycleEvent(
                new CloudServerExpired(
                    userId: (int) $user->getKey(),
                    serverId: (int) $server->getKey(),
                    serverName: $serverName,
                    expiresAt: $expiresAt->toIso8601String(),
                ),
            );
        }

        try {
            $decision = $this->terminationPolicyResolver()
                ->advance(
                    $server,
                );

            $this->logTerminationState(
                server: $server,
                decision: $decision,
            );

            if (! $decision->readyForDelete()) {
                return true;
            }

            $this->recordDeleteAttempt(
                (int) $server->getKey(),
            );

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
        $terminatedServer = Server::withTrashed()
            ->whereKey(
                $server->getKey(),
            )
            ->first();

        $terminatedAt = $terminatedServer
            ?->terminated_at
            ?->toIso8601String()
            ?? now()->toIso8601String();

        $this->dispatchLifecycleEvent(
            new CloudServerTerminated(
                userId: (int) $user->getKey(),
                serverId: (int) $server->getKey(),
                serverName: $serverName,
                expiresAt: $expiresAt->toIso8601String(),
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
                    return null;
                }

                if (
                    ! $server->isCloudProvisioned()
                    || ! $server->hasExpired()
                ) {
                    return null;
                }

                $firstAttempt = $server->termination_started_at === null;

                $server->forceFill([
                    /*
                     * Expired resources must immediately stop appearing
                     * as operationally available inside Coreflare.
                     */
                    'status' => ServerStatus::Inactive,
                    'termination_started_at' => $server->termination_started_at
                        ?? now(),
                ])->saveOrFail();

                return [
                    'server' => $server->refresh(),
                    'first_attempt' => $firstAttempt,
                ];
            },
        );
    }

    private function recordDeleteAttempt(
        int $serverId,
    ): void {
        DB::transaction(
            function () use ($serverId): void {
                /** @var Server|null $server */
                $server = Server::query()
                    ->whereKey($serverId)
                    ->lockForUpdate()
                    ->first();

                if (! $server instanceof Server) {
                    return;
                }

                $server->forceFill([
                    'termination_last_attempt_at' => now(),
                    'termination_attempts' => $server->termination_attempts + 1,
                    'termination_last_error' => null,
                ])->saveOrFail();
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
                    $message = $exception::class;
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

    private function terminationPolicyResolver(): CloudServerTerminationPolicyResolver
    {
        if ($this->terminationPolicies instanceof CloudServerTerminationPolicyResolver) {
            return $this->terminationPolicies;
        }

        return new CloudServerTerminationPolicyResolver(
            providers: $this->providers,
        );
    }

    private function logTerminationState(
        Server $server,
        CloudServerTerminationDecision $decision,
    ): void {
        $provider = $server->cloud_provider;

        logger()->info(
            'cloud_server.termination_state',
            [
                ...$decision->context,
                'server_id' => (int) $server->getKey(),
                'provider' => $provider instanceof CloudProviderType
                    ? $provider->value
                    : null,
                'state' => $decision->state->value,
            ],
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
