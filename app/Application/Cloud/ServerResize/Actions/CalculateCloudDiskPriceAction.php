<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudDiskPriceData;
use App\Models\Server;

final readonly class CalculateCloudDiskPriceAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    public function handle(
        Server $server,
        string $sizeId,
        int $diskGiB,
    ): CloudDiskPriceData {
        [$target, $catalog] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerResizeCatalogInterface::class,
        );

        return $catalog->calculateDiskPrice(
            region: $target->region,
            sizeId: $sizeId,
            diskGiB: $diskGiB,
        );
    }
}
