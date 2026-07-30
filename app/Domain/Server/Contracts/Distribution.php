<?php

declare(strict_types=1);

namespace App\Domain\Server\Contracts;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Server\Enums\DistributionType;
use App\Domain\Server\Enums\LinuxCommand;

interface Distribution
{
    /**
     * Returns the Linux distribution type.
     */
    public function type(): DistributionType;

    /**
     * Returns the shell command for the given application and operation.
     */
    public function command(
        ApplicationType $application,
        LinuxCommand $command,
    ): string;
}
