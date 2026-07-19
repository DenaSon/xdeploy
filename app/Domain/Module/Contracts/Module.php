<?php

declare(strict_types=1);

namespace App\Domain\Module\Contracts;

use App\Domain\Module\Enums\ModuleType;

interface Module
{
    /**
     * Returns the unique type of the module.
     */
    public function type(): ModuleType;

    /**
     * Returns the display name of the module.
     */
    public function name(): string;
}
