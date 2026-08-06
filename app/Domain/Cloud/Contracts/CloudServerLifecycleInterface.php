<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudServerActionData;

interface CloudServerLifecycleInterface
{
    public function powerOn(
        string $region,
        string $serverId,
    ): void;

    public function powerOff(
        string $region,
        string $serverId,
    ): void;

    public function reboot(
        string $region,
        string $serverId,
    ): void;

    public function deleteServer(
        string $region,
        string $serverId,
    ): void;

    /**
     * @return list<CloudServerActionData>
     */
    public function getAvailableActions(
        string $region,
        string $serverId,
    ): array;
}
