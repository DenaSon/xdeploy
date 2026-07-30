<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Services\ApplicationInstallationService;
use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class UninstallApplicationAction
{
    public function __construct(
        private ApplicationInstallationService $installer,
    ) {}

    public function execute(ApplicationType $type): void
    {
        $this->installer->uninstall($type);
    }
}
