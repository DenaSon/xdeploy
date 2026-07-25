<?php
declare(strict_types=1);

namespace App\Application\Module\Actions;

use App\Domain\Module\Services\ModuleService;

final readonly class InspectAllModulesAction
{
    public function __construct(
        private ModuleService $modules,
    )
    {
    }

    /**
     * @return array<int, array{
     *     type: \App\Domain\Module\Enums\ModuleType,
     *     name: string,
     *     info: \App\Domain\Module\DTOs\ModuleInfo,
     * }>
     */
    public function execute(): array
    {
        return $this->modules->inspectAll();
    }
}
