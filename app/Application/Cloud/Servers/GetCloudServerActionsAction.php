<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\DTOs\CloudServerActionData;

final readonly class GetCloudServerActionsAction
{
    public function __construct(
        private CloudServerLifecycleInterface $lifecycle,
    ) {}

    /**
     * @return list<CloudServerActionData>
     */
    public function handle(
        string $region,
        string $serverId,
    ): array {
        return $this->lifecycle->getAvailableActions(
            region: $region,
            serverId: $serverId,
        );
    }
}
