<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Contracts\ModuleRegistryInterface;
use App\Domain\Application\Contracts\StartableInterface;
use App\Domain\Application\DTOs\InstallReport;
use App\Domain\Application\Enums\ModuleType;
use App\Domain\Application\Services\InstallerService;
use App\Domain\Application\Services\ModuleLifecycleService;

final readonly class InstallModuleAction
{
    public function __construct(
        private InstallerService $installer,
        private ModuleLifecycleService $lifecycle,
        private ModuleRegistryInterface $registry,
    ) {}

    public function execute(ModuleType $type): InstallReport
    {
        $report = $this->installer->install($type);

        $module = $this->registry->find($type);

        if ($module instanceof StartableInterface) {
            $this->lifecycle->start($type);
        }

        return $report;
    }
}
