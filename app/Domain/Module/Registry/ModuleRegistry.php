<?php

declare(strict_types=1);

namespace App\Domain\Module\Registry;

use App\Domain\Module\Contracts\ModuleInterface;
use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\Enums\ModuleType;
use RuntimeException;

final readonly class ModuleRegistry implements ModuleRegistryInterface
{
    /**
     * @param  array<int, ModuleInterface>  $modules
     */
    public function __construct(
        private array $modules,
    ) {}

    /**
     * @return array<int, ModuleInterface>
     */
    public function all(): array
    {
        return $this->modules;
    }

    public function find(ModuleType $type): ModuleInterface
    {
        foreach ($this->modules as $module) {
            if ($module->type() === $type) {
                return $module;
            }
        }

        throw new RuntimeException(sprintf(
            'Module [%s] is not registered.',
            $type->value,
        ));
    }
}
