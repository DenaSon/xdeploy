<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\ResizeCloudRootDiskData;
use App\Domain\Cloud\DTOs\ResizeCloudServerData;

interface CloudServerResizerInterface
{
    /**
     * Resize the server flavor and requested disk capacity.
     */
    public function resizeServer(
        ResizeCloudServerData $data,
    ): void;

    /**
     * Increase the root disk capacity independently.
     */
    public function resizeRootDisk(
        ResizeCloudRootDiskData $data,
    ): void;
}
