<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Application\Server\Actions\EnsureServerOperationReadinessAction;
use App\Application\Server\Actions\PrepareServerPackageRepositoriesAction;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Services\ApplicationInstallationService;
use App\Domain\Application\Services\ApplicationLifecycleService;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Platform\Services\PlatformInstallationService;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Domain\Server\Services\SystemDependencyService;
use App\Domain\Shared\DTOs\InstallReport;
use App\Models\Server;

final readonly class InstallApplicationAction
{
    public function __construct(
        private ApplicationRegistryInterface $registry,
        private EnsureServerOperationReadinessAction $serverReadiness,
        private PrepareServerPackageRepositoriesAction $packageRepositories,
        private PrivilegedExecutionPreflight $preflight,
        private SystemDependencyService $systemDependencies,
        private PlatformInstallationService $platformInstallationService,
        private ApplicationInstallationService $installationService,
        private ApplicationLifecycleService $lifecycleService,
    ) {}

    public function execute(
        Server $server,
        ApplicationType $type,
    ): InstallReport {
        /*
         * ApplicationManager establishes the SSH session before invoking this
         * action. No distro-dependent command may run before this guard.
         *
         * This check is authoritative and must remain in the installation
         * workflow even when Presentation already validated the same server.
         */
        $operatingSystem = $this->serverReadiness
            ->handle();

        $application = $this->registry
            ->find($type);

        $this->preflight
            ->ensureRoot();

        /*
         * Provider-specific repository preparation belongs to the Server
         * operation boundary, not to Docker or any individual Application.
         * The action is a no-op for user-provided servers and non-Arvan
         * providers.
         */
        $this->packageRepositories->handle(
            server: $server,
            operatingSystem: $operatingSystem,
        );

        $requirements = $application
            ->requirements();

        $report = new InstallReport;

        $report = $report->merge(
            $this->systemDependencies->ensure(
                $requirements->systemPackages,
            ),
        );

        foreach (
            $requirements->platforms as $platformType
        ) {
            $report = $report->merge(
                $this->platformInstallationService->ensure(
                    $platformType,
                ),
            );
        }

        $report = $report->merge(
            $this->installationService->install(
                $type,
            ),
        );

        if (
            $application
            instanceof StartableInterface
        ) {
            $report = $report->merge(
                $this->lifecycleService->start(
                    $type,
                ),
            );
        }

        return $report;
    }
}
