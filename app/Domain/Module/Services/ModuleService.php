<?php

declare(strict_types=1);

namespace App\Domain\Module\Services;

use App\Domain\Module\Contracts\Module;
use App\Domain\Module\DTOs\ModuleInfo;
use App\Domain\Module\Enums\ModuleType;
use App\Domain\Module\Registry\ModuleRegistry;
use RuntimeException;

final readonly class ModuleService
{
    public function __construct(
        private ModuleRegistry $registry,
    ) {}

    /**
     * @return array<int, Module>
     */
    public function all(): array
    {
        return $this->registry->all();
    }

    public function find(ModuleType $type): Module
    {
        foreach ($this->registry->all() as $module) {
            if ($module->type() === $type) {
                return $module;
            }
        }

        throw new RuntimeException("Module [{$type->value}] is not registered.");
    }

    public function inspect(ModuleType $type): ModuleInfo
    {
        return $this->find($type)->inspect();
    }

    /**
     * @return array<string, ModuleInfo>
     */
    public function inspectAll(): array
    {
        $modules = [];

        foreach ($this->registry->all() as $module) {
            $modules[$module->type()->value] = $module->inspect();
        }

        return $modules;
    }
}
