<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

trait CreatesPrivilegedExecutor
{
    protected function privilegedExecutor(
        SSHConnectionInterface $ssh,
    ): PrivilegedCommandExecutor {
        return new PrivilegedCommandExecutor(
            ssh: $ssh,
            preflight: new PrivilegedExecutionPreflight(
                $ssh,
            ),
        );
    }
}
