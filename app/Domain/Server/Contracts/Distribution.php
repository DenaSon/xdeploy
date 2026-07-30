<?php

declare(strict_types=1);

namespace App\Domain\Server\Contracts;

use App\Domain\Application\Enums\ModuleType;
use App\Domain\Server\Enums\DistributionType;
use App\Domain\Server\Enums\LinuxCommand;

interface Distribution
{
    /**
     * Returns the Linux distribution type.
     */
    public function type(): DistributionType;

    /**
     * Returns the shell command for the given module and operation.
     */
    public function command(
        ModuleType $module,
        LinuxCommand $command,
    ): string;
}
