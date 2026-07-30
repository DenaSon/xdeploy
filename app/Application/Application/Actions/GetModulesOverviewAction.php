<?php

declare(strict_types=1);

namespace App\Application\Application\Actions;

use App\Domain\Application\DTOs\ModuleInfo;
use App\Domain\Application\Enums\ModuleType;
use App\Domain\Application\Services\ModuleService;

final readonly class GetModulesOverviewAction
{
    public function __construct(
        private ModuleService $modules,
    ) {}

    /**
     * @return array<int, array{
     *     type: ModuleType,
     *     name: string,
     *     info: ModuleInfo,
     * }>
     */
    public function handle(): array
    {
        return $this->modules->inspectAll();
    }
}
