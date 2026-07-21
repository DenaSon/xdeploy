<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Module\Services\ModuleService;

final readonly class DashboardService
{
    public function __construct(
        private ModuleService $moduleService,
    ) {}

    public function overview(): array
    {
        return [
            'modules' => $this->moduleService->inspectAll(),
        ];
    }
}
