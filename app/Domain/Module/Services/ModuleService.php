<?php

declare(strict_types=1);

namespace App\Domain\Module\Services;

use App\Domain\Module\Contracts\ModuleInterface;
use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\DTOs\ModuleInfo;
use App\Domain\Module\Enums\ModuleType;

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
