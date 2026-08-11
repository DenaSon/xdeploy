<?php

declare(strict_types=1);

namespace App\Application\Cloud\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CloudServerExpired implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $userId,
        public int $serverId,
        public string $serverName,
        public string $expiresAt,
    ) {}
}
