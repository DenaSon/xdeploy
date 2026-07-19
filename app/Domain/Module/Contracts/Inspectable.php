<?php

declare(strict_types=1);

namespace App\Domain\Module\Contracts;

use App\Domain\Module\DTOs\ModuleInfo;

interface Inspectable
{
    /**
     * * Inspect the current module on the server.
     * */
    public function inspect(): ModuleInfo;
}
