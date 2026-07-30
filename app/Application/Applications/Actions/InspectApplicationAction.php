<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Services\ApplicationService;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;
use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class InspectApplicationAction
{
    public function __construct(
        private ApplicationService $modules,
    ) {}

    public function execute(ApplicationType $type): ApplicationInfo
    {
        return $this->modules->inspect($type);
    }
}
