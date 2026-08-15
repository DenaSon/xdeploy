<?php

declare(strict_types=1);

namespace App\Application\Applications\Manager;

use App\Application\Applications\Actions\InstallApplicationAction;
use App\Application\Applications\Actions\RestartApplicationAction;
use App\Application\Applications\Actions\StartApplicationAction;
use App\Application\Applications\Actions\StopApplicationAction;
use App\Application\Applications\Actions\UninstallApplicationAction;
use App\Application\Applications\Operations\Contracts\ApplicationOperationProgressReporter;
use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationOperationStage;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Shared\DTOs\InstallReport;
use App\Models\Server;
use App\Models\User;
use Closure;

final readonly class ApplicationManager
{
    public function __construct(
        private ConnectServerAction $connectServerAction,
        private ApplicationRegistryInterface $applicationRegistry,
        private InstallApplicationAction $installApplicationAction,
        private UninstallApplicationAction $uninstallApplicationAction,
        private StartApplicationAction $startApplicationAction,
        private StopApplicationAction $stopApplicationAction,
        private RestartApplicationAction $restartApplicationAction,
    ) {}

    public function inspect(
        User $user,
        Server $server,
        ApplicationType $type,
    ): ApplicationInfo {
        return $this->onServer(
            user: $user,
            server: $server,
            operation: fn (Server $ownedServer): ApplicationInfo => $this->applicationRegistry
                ->find($type)
                ->inspect(),
        );
    }

    public function install(
        User $user,
        Server $server,
        ApplicationType $type,
        ?ApplicationOperationProgressReporter $progressReporter = null,
    ): InstallReport {
        $progressReporter?->report(
            ApplicationOperationStage::Connecting,
        );

        return $this->onServer(
            user: $user,
            server: $server,
            operation: function (Server $ownedServer) use (
                $type,
                $progressReporter,
            ): InstallReport {
                $progressReporter?->report(
                    ApplicationOperationStage::CheckingServer,
                );

                return $this->installApplicationAction
                    ->execute(
                        server: $ownedServer,
                        type: $type,
                        progressReporter: $progressReporter,
                    );
            },
        );
    }

    public function uninstall(
        User $user,
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            user: $user,
            server: $server,
            operation: fn (Server $ownedServer) => $this->uninstallApplicationAction
                ->execute($type),
        );
    }

    public function start(
        User $user,
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            user: $user,
            server: $server,
            operation: fn (Server $ownedServer) => $this->startApplicationAction
                ->execute($type),
        );
    }

    public function stop(
        User $user,
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            user: $user,
            server: $server,
            operation: fn (Server $ownedServer) => $this->stopApplicationAction
                ->execute($type),
        );
    }

    public function restart(
        User $user,
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            user: $user,
            server: $server,
            operation: fn (Server $ownedServer) => $this->restartApplicationAction
                ->execute($type),
        );
    }

    /**
     * @template TResult
     *
     * @param  Closure(Server): TResult  $operation
     * @return TResult
     */
    private function onServer(
        User $user,
        Server $server,
        Closure $operation,
    ): mixed {
        /*
         * Re-resolve the server through the authenticated user.
         *
         * Presentation mistakes must never allow a remote operation
         * against another tenant's server. The authoritative persisted
         * instance is also passed into the operation so provider metadata
         * cannot be supplied by an untrusted caller-side model instance.
         */
        $ownedServer = Server::query()
            ->ownedBy($user)
            ->whereKey(
                $server->getKey(),
            )
            ->firstOrFail();

        $this->connectServerAction->handle(
            $ownedServer,
        );

        return $operation(
            $ownedServer,
        );
    }
}
