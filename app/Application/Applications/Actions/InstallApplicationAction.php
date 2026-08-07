<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Application\Server\Actions\EnsureServerOperationReadinessAction;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Services\ApplicationInstallationService;
use App\Domain\Application\Services\ApplicationLifecycleService;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Platform\Services\PlatformInstallationService;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Domain\Server\Services\SystemDependencyService;
use App\Domain\Shared\DTOs\InstallReport;

final readonly class InstallApplicationAction
{
    private const int MAX_EXECUTION_SECONDS = 400;

    public function __construct(
        private ApplicationRegistryInterface $registry,
        private EnsureServerOperationReadinessAction $serverReadiness,
        private PrivilegedExecutionPreflight $preflight,
        private SystemDependencyService $systemDependencies,
        private PlatformInstallationService $platformInstallationService,
        private ApplicationInstallationService $installationService,
        private ApplicationLifecycleService $lifecycleService,
    ) {}

    public function execute(
        ApplicationType $type,
    ): InstallReport {
        $this->extendExecutionTime();

        /*
         * ApplicationManager establishes the SSH session before invoking this
         * action. No distro-dependent command may run before this guard.
         *
         * This check is authoritative and must remain in the installation
         * workflow even when Presentation already validated the same server.
         */
        $this->serverReadiness
            ->handle();

        $application = $this->registry
            ->find($type);

        $this->preflight
            ->ensureRoot();

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

    private function extendExecutionTime(): void
    {
        if (! function_exists('set_time_limit')) {
            logger()->warning(
                'installation.execution_time_extension_unavailable',
            );

            return;
        }

        $extended = set_time_limit(
            self::MAX_EXECUTION_SECONDS,
        );

        if (! $extended) {
            logger()->warning(
                'installation.execution_time_extension_failed',
                [
                    'requested_seconds' => self::MAX_EXECUTION_SECONDS,
                    'current_limit' => ini_get('max_execution_time'),
                ],
            );

            return;
        }

        logger()->info(
            'installation.execution_time_extended',
            [
                'max_execution_seconds' => self::MAX_EXECUTION_SECONDS,
            ],
        );
    }
}
