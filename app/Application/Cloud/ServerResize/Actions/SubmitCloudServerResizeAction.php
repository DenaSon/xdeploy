<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudServerData;
use App\Models\Server;

final readonly class SubmitCloudServerResizeAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    public function handle(
        Server $server,
        ResizeCloudServerData $data,
    ): void {
        [$target, $resizer] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerResizerInterface::class,
        );

        $resizer->resizeServer(
            new ResizeCloudServerData(
                regionId: $target->region,
                serverId: $target->serverId,
                targetSizeId: $data->targetSizeId,
                targetDiskGiB: $data->targetDiskGiB,
            ),
        );
    }
}
