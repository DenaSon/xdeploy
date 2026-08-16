<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudRootDiskData;
use App\Models\Server;

final readonly class SubmitCloudRootDiskResizeAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    public function handle(
        Server $server,
        ResizeCloudRootDiskData $data,
    ): void {
        [$target, $resizer] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerResizerInterface::class,
        );

        $resizer->resizeRootDisk(
            new ResizeCloudRootDiskData(
                regionId: $target->region,
                serverId: $target->serverId,
                targetDiskGiB: $data->targetDiskGiB,
            ),
        );
    }
}
