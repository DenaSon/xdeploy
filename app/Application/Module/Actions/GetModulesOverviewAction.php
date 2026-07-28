<?php

declare(strict_types=1);

namespace App\Application\Module\Actions;

use App\Domain\Module\DTOs\ModuleInfo;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Services\ModuleService;

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
