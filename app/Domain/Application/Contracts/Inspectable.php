<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;
use App\Domain\Application\Shared\DTOs\ApplicationInfo;

interface Inspectable
{
    /**
     * * Inspect the current module on the server.
     * */
    public function inspect(): ApplicationInfo;
}
