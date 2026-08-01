<?php

declare(strict_types=1);

namespace App\Application\Applications\Manager;

use App\Application\Applications\Actions\GetApplicationOverviewAction;
use App\Application\Applications\Actions\InstallApplicationAction;
use App\Application\Applications\Actions\RestartApplicationAction;
use App\Application\Applications\Actions\StartApplicationAction;
use App\Application\Applications\Actions\StopApplicationAction;
use App\Application\Applications\Actions\UninstallApplicationAction;
use App\Application\Server\Actions\ConnectServerAction;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Shared\DTOs\InstallReport;
use App\Models\Server;
use Closure;

final readonly class ApplicationManager
{
    public function __construct(
        private ConnectServerAction $connectServerAction,
        private ApplicationRegistryInterface $applicationRegistry,
        private GetApplicationOverviewAction $getApplicationOverviewAction,
        private InstallApplicationAction $installApplicationAction,
        private UninstallApplicationAction $uninstallApplicationAction,
        private StartApplicationAction $startApplicationAction,
        private StopApplicationAction $stopApplicationAction,
        private RestartApplicationAction $restartApplicationAction,
    ) {}

    /**
     * @return array<int, array{
     *     type: ApplicationType,
     *     name: string,
     *     info: ApplicationInfo,
     * }>
     */
    public function overview(Server $server): array
    {
        return $this->onServer(
            $server,
            fn (): array => $this->getApplicationOverviewAction->execute(),
        );
    }

    public function inspect(
        Server $server,
        ApplicationType $type,
    ): ApplicationInfo {
        return $this->onServer(
            $server,
            fn (): ApplicationInfo => $this->applicationRegistry
                ->find($type)
                ->inspect(),
        );
    }

    public function install(
        Server $server,
        ApplicationType $type,
    ): InstallReport {
        return $this->onServer(
            $server,
            fn (): InstallReport => $this->installApplicationAction
                ->execute($type),
        );
    }

    public function uninstall(
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            $server,
            fn () => $this->uninstallApplicationAction->execute($type),
        );
    }

    public function start(
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            $server,
            fn () => $this->startApplicationAction->execute($type),
        );
    }

    public function stop(
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            $server,
            fn () => $this->stopApplicationAction->execute($type),
        );
    }

    public function restart(
        Server $server,
        ApplicationType $type,
    ): void {
        $this->onServer(
            $server,
            fn () => $this->restartApplicationAction->execute($type),
        );
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $operation
     * @return TResult
     */
    private function onServer(
        Server $server,
        Closure $operation,
    ): mixed {
        $this->connectServerAction->handle($server);

        return $operation();
    }
}
