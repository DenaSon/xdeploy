<?php

declare(strict_types=1);

namespace App\Application\Module\Actions;

use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\Contracts\StartableInterface;
use App\Domain\Module\DTOs\InstallReport;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Services\InstallerService;
use App\Domain\Module\Services\ModuleLifecycleService;

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
