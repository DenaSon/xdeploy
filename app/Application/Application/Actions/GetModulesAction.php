<?php

declare(strict_types=1);

namespace App\Application\Application\Actions;

use App\Domain\Application\Services\ModuleService;

final readonly class GetModulesAction
{
    public function __construct(
        private ModuleService $modules,
    ) {}

    /**
     * @return array<int, array{
     *     type: string,
     *     name: string,
     * }>
     */
    public function handle(): array
    {
        return array_map(
            static fn ($module): array => [
                'type' => $module->type()->value,
                'name' => $module->name(),
            ],
            $this->modules->all(),
        );
    }
}
