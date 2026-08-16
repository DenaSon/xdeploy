<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;
use App\Models\Server;

final readonly class GetCloudSizeAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    public function handle(
        Server $server,
        string $sizeId,
    ): CloudSizeData {
        [$target, $catalog] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerResizeCatalogInterface::class,
        );

        return $catalog->findSize(
            region: $target->region,
            sizeId: $sizeId,
        );
    }
}
