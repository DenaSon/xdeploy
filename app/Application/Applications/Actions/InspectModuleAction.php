<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\DTOs\ModuleInfo;
use App\Domain\Application\Enums\ModuleType;
use App\Domain\Application\Services\ModuleService;

final readonly class InspectModuleAction
{
    public function __construct(
        private ModuleService $modules,
    ) {}

    public function execute(ModuleType $type): ModuleInfo
    {
        return $this->modules->inspect($type);
    }
}
