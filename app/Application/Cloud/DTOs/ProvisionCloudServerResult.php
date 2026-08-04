<?php

declare(strict_types=1);

namespace App\Application\Cloud\DTOs;

use App\Domain\Cloud\DTOs\CloudServerData;
use App\Models\Server;

final readonly class ProvisionCloudServerResult
{
    public function __construct(
        public Server $server,
        public CloudServerData $cloudServer,
        public int $pollAttempts,
    ) {}
}
