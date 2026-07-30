<?php

declare(strict_types=1);

namespace App\Domain\Application\ValueObjects;

use App\Domain\Application\Enums\ModuleType;

final readonly class ModuleDependency
{
    public function __construct(
        public ModuleType $type,
    ) {}
}
