<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Models\Server;

final readonly class ListAvailableServerResizePlansAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    /**
     * @return list<CloudSizeData>
     */
    public function handle(Server $server): array
    {
        [$target, $catalog] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerResizeCatalogInterface::class,
        );

        return $catalog->listServerResizePlans(
            region: $target->region,
            serverId: $target->serverId,
        );
    }
}
