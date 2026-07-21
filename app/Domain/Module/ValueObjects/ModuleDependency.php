<?php

declare(strict_types=1);

namespace App\Domain\Module\ValueObjects;

use App\Domain\Module\Enums\ModuleType;

final readonly class ModuleDependency
{
    public function __construct(
        public ModuleType $type,
    ) {}
}
