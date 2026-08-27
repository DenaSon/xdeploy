<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudVolumeData;

interface CloudVolumeManagerInterface
{
    /**
     * @return list<CloudVolumeData>
     */
    public function listVolumes(string $region): array;

    /**
     * @return list<CloudVolumeData>
     */
    public function listAttachedToServer(
        string $region,
        string $serverId,
    ): array;

    public function findVolume(
        string $region,
        string $volumeId,
    ): CloudVolumeData;

    public function deleteVolume(
        string $region,
        string $volumeId,
    ): void;
}
