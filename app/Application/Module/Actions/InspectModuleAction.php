<?php
declare(strict_types=1);

namespace App\Application\Module\Actions;

use App\Domain\Module\DTOs\ModuleInfo;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Services\ModuleService;

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
