<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Models\Server;

final readonly class PowerOnCloudServerAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    public function handle(Server $server): void
    {
        [$target, $lifecycle] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerLifecycleInterface::class,
        );

        $lifecycle->powerOn(
            region: $target->region,
            serverId: $target->serverId,
        );
    }
}
