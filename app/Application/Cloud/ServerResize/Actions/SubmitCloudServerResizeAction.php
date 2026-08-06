<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerResize\Actions;

use App\Domain\Cloud\Contracts\CloudServerResizerInterface;
use App\Domain\Cloud\DTOs\ResizeCloudServerData;

final readonly class SubmitCloudServerResizeAction
{
    public function __construct(
        private CloudServerResizerInterface $resizer,
    ) {}

    public function handle(
        ResizeCloudServerData $data,
    ): void {
        $this->resizer->resizeServer(
            $data,
        );
    }
}
