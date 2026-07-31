<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

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
    public function __construct(
        private ApplicationRegistryInterface $registry,
        private PrivilegedExecutionPreflight $preflight,
        private SystemDependencyService $systemDependencies,
        private PlatformInstallationService $platformInstallationService,
        private ApplicationInstallationService $installationService,
        private ApplicationLifecycleService $lifecycleService,
    ) {}

    public function execute(ApplicationType $type): InstallReport
    {
        $application = $this->registry->find($type);

        $this->preflight->ensureRoot();

        $requirements = $application->requirements();

        $report = new InstallReport;

        $report = $report->merge(
            $this->systemDependencies->ensure(
                $requirements->systemPackages,
            ),
        );

        foreach ($requirements->platforms as $platformType) {
            $report = $report->merge(
                $this->platformInstallationService->ensure(
                    $platformType,
                ),
            );
        }

        $report = $report->merge(
            $this->installationService->install($type),
        );

        if ($application instanceof StartableInterface) {
            $report = $report->merge(
                $this->lifecycleService->start($type),
            );
        }

        return $report;
    }
}
