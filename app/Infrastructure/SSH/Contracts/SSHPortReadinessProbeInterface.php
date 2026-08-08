<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Contracts;

use App\Models\Server;

interface SSHPortReadinessProbeInterface
{
    public function waitUntilReady(
        Server $server,
    ): bool;
}
