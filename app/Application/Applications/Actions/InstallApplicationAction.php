<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\Services\ApplicationInstallationService;
use App\Domain\Application\Services\ApplicationLifecycleService;
use App\Domain\Application\Shared\DTOs\InstallReport;
use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class InstallApplicationAction
{
    public function __construct(
        private ApplicationInstallationService $installationService,
        private ApplicationLifecycleService $lifecycleService,
        private ApplicationRegistryInterface $registry,
    ) {}

    public function execute(ApplicationType $type): InstallReport
    {
        $report = $this->installationService->install($type);

        $application = $this->registry->find($type);

        if ($application instanceof StartableInterface) {
            $this->lifecycleService->start($type);
        }

        return $report;
    }
}
