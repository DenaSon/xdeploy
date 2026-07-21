<?php

declare(strict_types=1);

namespace App\Domain\Module\Contracts;

use App\Domain\Module\Enums\ModuleType;

interface ModuleRegistryInterface
{
    /**
     * @return array<int, ModuleInterface>
     */
    public function all(): array;

    public function find(ModuleType $type): ModuleInterface;
}
