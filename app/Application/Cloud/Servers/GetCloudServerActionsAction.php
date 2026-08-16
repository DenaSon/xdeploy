<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudServerActionData;
use App\Models\Server;

final readonly class GetCloudServerActionsAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    /**
     * @return list<CloudServerActionData>
     */
    public function handle(Server $server): array
    {
        [$target, $lifecycle] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerLifecycleInterface::class,
        );

        return $lifecycle->getAvailableActions(
            region: $target->region,
            serverId: $target->serverId,
        );
    }
}
