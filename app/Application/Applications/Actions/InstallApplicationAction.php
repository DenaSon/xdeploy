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
        private ApplicationInstallationService $installer,
        private ApplicationLifecycleService $lifecycle,
        private ApplicationRegistryInterface $registry,
    ) {}

    public function execute(ApplicationType $type): InstallReport
    {
        $report = $this->installer->install($type);

        $module = $this->registry->find($type);

        if ($module instanceof StartableInterface) {
            $this->lifecycle->start($type);
        }

        return $report;
    }
}
