<?php

declare(strict_types=1);

namespace App\Domain\Application\Services;

use App\Domain\Application\Contracts\ModuleInterface;
use App\Domain\Application\Contracts\ModuleRegistryInterface;
use App\Domain\Application\DTOs\ModuleInfo;
use App\Domain\Application\Enums\ModuleType;

final readonly class ModuleService
{
    public function __construct(
        private ModuleRegistryInterface $registry,
    ) {}

    /**
     * @return array<int, ModuleInterface>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    public function inspect(ModuleType $type): ModuleInfo
    {
        return $this->registry
            ->find($type)
            ->inspect();
    }

    /**
     * @return array<int, array{
     *     type: ModuleType,
     *     name: string,
     *     info: ModuleInfo,
     * }>
     */
    public function inspectAll(): array
    {
        $modules = [];

        foreach ($this->registry->all() as $module) {
            $info = $module->inspect();

            $modules[] = [
                'type' => $module->type(),
                'name' => $module->name(),
                'info' => $info,
            ];
        }

        return $modules;
    }
}
