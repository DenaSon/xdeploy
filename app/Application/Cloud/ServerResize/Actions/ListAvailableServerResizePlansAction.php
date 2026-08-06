<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Domain\Cloud\Contracts\CloudServerResizeCatalogInterface;
use App\Domain\Cloud\DTOs\CloudSizeData;

final readonly class ListAvailableServerResizePlansAction
{
    public function __construct(
        private CloudServerResizeCatalogInterface $catalog,
    ) {}

    /**
     * @return list<CloudSizeData>
     */
    public function handle(
        string $region,
        string $serverId,
    ): array {
        return $this->catalog->listServerResizePlans(
            region: $region,
            serverId: $serverId,
        );
    }
}
