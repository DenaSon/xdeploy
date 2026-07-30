<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Application\Applications\Actions\InspectApplicationsAction;

final readonly class DashboardService
{
    public function __construct(
        private InspectApplicationsAction $inspectApplications,
    ) {}

    public function overview(): array
    {
        return [
            'applications' => $this->inspectApplications->execute(),
        ];
    }
}
