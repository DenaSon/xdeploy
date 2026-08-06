<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudRootDiskData;

final readonly class SubmitCloudRootDiskResizeAction
{
    public function __construct(
        private CloudServerResizerInterface $resizer,
    ) {}

    public function handle(
        ResizeCloudRootDiskData $data,
    ): void {
        $this->resizer->resizeRootDisk(
            $data,
        );
    }
}
