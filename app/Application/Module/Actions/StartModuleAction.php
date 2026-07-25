<?php

declare(strict_types=1);

namespace App\Application\Module\Actions;

use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Services\ModuleLifecycleService;

final readonly class StartModuleAction
{
    public function __construct(
        private ModuleLifecycleService $lifecycle,
    ) {}

    public function execute(ModuleType $type): void
    {
        $this->lifecycle->start($type);
    }
}
