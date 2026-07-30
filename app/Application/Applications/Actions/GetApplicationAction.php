<?php

declare(strict_types=1);

namespace App\Application\Applications\Actions;

use App\Domain\Application\Services\ApplicationService;

final readonly class GetApplicationAction
{
    public function __construct(
        private ApplicationService $modules,
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
