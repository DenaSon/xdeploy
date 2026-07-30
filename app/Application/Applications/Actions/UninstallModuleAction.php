<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Enums\ModuleType;
use App\Domain\Application\Services\InstallerService;

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
