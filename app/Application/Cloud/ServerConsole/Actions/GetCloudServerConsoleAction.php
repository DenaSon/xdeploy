<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerConsole\Actions;

use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\DTOs\CloudServerConsoleData;

final readonly class GetCloudServerConsoleAction
{
    public function __construct(
        private CloudServerConsoleInterface $console,
    ) {}

    public function execute(
        string $region,
        string $serverId,
    ): CloudServerConsoleData {
        return $this->console->getVncConsole(
            region: $region,
            serverId: $serverId,
        );
    }
}
