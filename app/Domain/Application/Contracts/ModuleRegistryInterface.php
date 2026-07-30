<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;

use App\Domain\Application\Enums\ModuleType;

interface ModuleRegistryInterface
{
    /**
     * @return array<int, ModuleInterface>
     */
    public function all(): array;

    public function find(ModuleType $type): ModuleInterface;
}
