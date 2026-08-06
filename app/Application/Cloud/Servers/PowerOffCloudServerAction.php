<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;

final readonly class PowerOffCloudServerAction
{
    public function __construct(
        private CloudServerLifecycleInterface $lifecycle,
    ) {}

    public function handle(
        string $region,
        string $serverId,
    ): void {
        $this->lifecycle->powerOff(
            region: $region,
            serverId: $serverId,
        );
    }
}
