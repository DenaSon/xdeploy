<?php

declare(strict_types=1);

namespace App\Application\Module\Actions;

use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Services\InstallerService;

final readonly class UninstallModuleAction
{
    public function __construct(
        private InstallerService $installer,
    ) {}

    public function execute(ModuleType $type): void
    {
        $this->installer->uninstall($type);
    }
}
