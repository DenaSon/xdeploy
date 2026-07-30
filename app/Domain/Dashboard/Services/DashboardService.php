<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Application\Applications\Actions\InspectAllApplicationAction;

final readonly class DashboardService
{
    public function __construct(
        private InspectAllApplicationAction $inspectModules,
    ) {}

    public function overview(): array
    {
        return [
            'modules' => $this->inspectModules->execute(),
        ];
    }
}
