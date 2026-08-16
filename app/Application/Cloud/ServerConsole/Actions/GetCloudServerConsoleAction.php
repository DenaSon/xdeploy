<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerConsole\Actions;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerConsoleInterface;
use App\Domain\Cloud\DTOs\CloudServerConsoleData;
use App\Models\Server;

final readonly class GetCloudServerConsoleAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    public function execute(Server $server): CloudServerConsoleData
    {
        [$target, $console] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerConsoleInterface::class,
        );

        return $console->getVncConsole(
            region: $target->region,
            serverId: $target->serverId,
        );
    }
}
