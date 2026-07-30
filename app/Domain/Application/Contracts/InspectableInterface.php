<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;

use App\Domain\Application\Shared\DTOs\ApplicationInfo;

interface InspectableInterface
{
    public function inspect(): ApplicationInfo;
}
