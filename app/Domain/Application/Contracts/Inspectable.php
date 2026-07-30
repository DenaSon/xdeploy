<?php

declare(strict_types=1);

namespace App\Domain\Application\Contracts;

use App\Domain\Application\DTOs\ModuleInfo;

interface Inspectable
{
    /**
     * * Inspect the current module on the server.
     * */
    public function inspect(): ModuleInfo;
}
