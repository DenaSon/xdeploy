<?php

declare(strict_types=1);

namespace App\Application\Module\Actions;

use App\Domain\Module\DTOs\InstallReport;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Services\InstallerService;

final readonly class InstallModuleAction
{
    public function __construct(
        private InstallerService $installer,
    ) {}

    public function execute(ModuleType $type): InstallReport
    {
        return $this->installer->install($type);
    }
}
