<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Services\ApplicationLifecycleService;
use App\Domain\Application\Shared\Enums\ApplicationType;

final readonly class StartApplicationAction
{
    public function __construct(
        private ApplicationLifecycleService $lifecycle,
    ) {}

    public function execute(ApplicationType $type): void
    {
        $this->lifecycle->start($type);
    }
}
