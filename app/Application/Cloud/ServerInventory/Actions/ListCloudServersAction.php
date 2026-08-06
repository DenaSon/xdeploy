<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerInventory\Actions;

use App\Domain\Cloud\Contracts\CloudServerInventoryInterface;
use App\Domain\Cloud\DTOs\CloudServerData;

final readonly class ListCloudServersAction
{
    public function __construct(
        private CloudServerInventoryInterface $inventory,
    ) {}

    /**
     * @return list<CloudServerData>
     */
    public function execute(
        string $region,
    ): array {
        return $this->inventory->listServers(
            region: $region,
        );
    }
}
