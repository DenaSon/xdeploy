<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers\Termination;

use App\Models\Server;

interface CloudServerTerminationPolicy
{
    /**
     * Advance any provider-specific prerequisite and return the resulting
     * termination state for this scheduler pass.
     */
    public function advance(
        Server $server,
    ): CloudServerTerminationDecision;
}
