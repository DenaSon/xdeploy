<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Enums\ModuleType;
use App\Domain\Application\Services\ModuleLifecycleService;

final readonly class RestartModuleAction
{
    public function __construct(
        private ModuleLifecycleService $lifecycle,
    ) {}

    public function execute(ModuleType $type): void
    {
        $this->lifecycle->restart($type);
    }
}
