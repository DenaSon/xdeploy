<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;

use App\Domain\Application\DTOs\ModuleInfo;

interface InspectableInterface
{
    public function inspect(): ModuleInfo;
}
