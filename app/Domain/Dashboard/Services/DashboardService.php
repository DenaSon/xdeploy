<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Application\Applications\Actions\InspectAllModulesAction;

final readonly class DashboardService
{
    public function __construct(
        private InspectAllModulesAction $inspectModules,
    ) {}

    public function overview(): array
    {
        return [
            'modules' => $this->inspectModules->execute(),
        ];
    }
}
