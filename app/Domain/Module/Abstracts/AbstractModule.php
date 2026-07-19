<?php

declare(strict_types=1);

namespace App\Domain\Module\Abstracts;

use App\Domain\Module\Contracts\Inspectable;
use App\Domain\Module\Contracts\Module;
use App\Domain\Module\Enums\ModuleType;

abstract class AbstractModule implements Inspectable, Module
{
    abstract public function type(): ModuleType;

    abstract public function name(): string;
}
