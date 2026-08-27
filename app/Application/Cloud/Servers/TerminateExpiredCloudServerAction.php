<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Application\Cloud\Events\CloudServerExpired;
use App\Application\Cloud\Events\CloudServerTerminated;
use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final readonly class TerminateExpiredCloudServerAction
{
    private const int LIARA_MINIMUM_EXPIRED_MINUTES = 5;

    private const int LIARA_MINIMUM_RESOURCE_AGE_HOURS = 24;

    public function __construct(
        private DeleteCloudServerAction $deleteCloudServer,
        private ?CloudProviderRegistryInterface $providers = null,
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
            if (! $this->isReadyForProviderDeletion($server)) {
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
     * Liara requires a stopped VM before deletion and rejects deletion of
     * resources younger than 24 hours. Expected waiting states are not
     * failures; the five-minute scheduler will evaluate them again.
     */
    private function isReadyForProviderDeletion(
        Server $server,
    ): bool {
        if ($server->cloud_provider !== CloudProviderType::Liara) {
            return true;
        }

        if (! $this->providers instanceof CloudProviderRegistryInterface) {
            throw new LogicException(
                'Cloud provider registry is required for Liara termination readiness checks.',
            );
        }

        $region = trim((string) $server->cloud_region);
        $providerServerId = trim((string) $server->cloud_server_id);

        if ($region === '' || $providerServerId === '') {
            throw new LogicException(
                sprintf(
                    'Liara Server [%d] has incomplete cloud metadata.',
                    $server->getKey(),
                ),
            );
        }

        try {
            $providerServer = $this->providers
                ->resolve(CloudProviderType::Liara)
                ->findServer(
                    region: $region,
                    serverId: $providerServerId,
                );
        } catch (CloudResourceNotFoundException) {
            /*
             * The desired external state is already reached. Continue to
             * DeleteCloudServerAction so its existing not-found handling can
             * finalize the local record consistently.
             */
            return true;
        }

        if ($providerServer->isRunning()) {
            /** @var CloudServerLifecycleInterface $lifecycle */
            $lifecycle = $this->providers->resolveCapability(
                provider: CloudProviderType::Liara,
                capability: CloudServerLifecycleInterface::class,
            );

            $lifecycle->powerOff(
                region: $region,
                serverId: $providerServerId,
            );

            logger()->info(
                'cloud_server.termination_poweroff_requested',
                [
                    'server_id' => (int) $server->getKey(),
                    'provider' => CloudProviderType::Liara->value,
                    'provider_server_id' => $providerServerId,
                ],
            );

            return false;
        }

        if (! $providerServer->isStopped()) {
            logger()->info(
                'cloud_server.termination_waiting_for_power_state',
                [
                    'server_id' => (int) $server->getKey(),
                    'provider' => CloudProviderType::Liara->value,
                    'power_state' => $providerServer->powerState->value,
                ],
            );

            return false;
        }

        $expiresAt = $server->expires_at;

        if (
            $expiresAt === null
            || $expiresAt->greaterThan(
                now()->subMinutes(self::LIARA_MINIMUM_EXPIRED_MINUTES),
            )
        ) {
            logger()->info(
                'cloud_server.termination_waiting_for_expiration_grace',
                [
                    'server_id' => (int) $server->getKey(),
                    'provider' => CloudProviderType::Liara->value,
                    'expires_at' => $expiresAt?->toIso8601String(),
                    'minimum_minutes' => self::LIARA_MINIMUM_EXPIRED_MINUTES,
                ],
            );

            return false;
        }

        if ($providerServer->createdAt === null) {
            logger()->warning(
                'cloud_server.termination_waiting_for_provider_created_at',
                [
                    'server_id' => (int) $server->getKey(),
                    'provider' => CloudProviderType::Liara->value,
                    'provider_server_id' => $providerServerId,
                ],
            );

            return false;
        }

        $minimumCreatedAt = now()
            ->subHours(self::LIARA_MINIMUM_RESOURCE_AGE_HOURS)
            ->toDateTimeImmutable();

        if ($providerServer->createdAt > $minimumCreatedAt) {
            logger()->info(
                'cloud_server.termination_waiting_for_provider_minimum_age',
                [
                    'server_id' => (int) $server->getKey(),
                    'provider' => CloudProviderType::Liara->value,
                    'provider_created_at' => $providerServer->createdAt->format(DATE_ATOM),
                    'minimum_hours' => self::LIARA_MINIMUM_RESOURCE_AGE_HOURS,
                ],
            );

            return false;
        }

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
