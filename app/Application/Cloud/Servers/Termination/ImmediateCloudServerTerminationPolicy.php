<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers\Termination;

use App\Models\Server;

final readonly class ImmediateCloudServerTerminationPolicy implements CloudServerTerminationPolicy
{
    public function advance(
        Server $server,
    ): CloudServerTerminationDecision {
        return new CloudServerTerminationDecision(
            state: CloudServerTerminationState::ReadyForDelete,
        );
    }
}
