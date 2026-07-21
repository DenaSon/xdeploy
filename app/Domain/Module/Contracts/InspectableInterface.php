<?php

declare(strict_types=1);

namespace App\Domain\Module\Contracts;

use App\Domain\Module\DTOs\ModuleInfo;

interface InspectableInterface
{
    public function inspect(): ModuleInfo;
}
