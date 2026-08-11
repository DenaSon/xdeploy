<?php

declare(strict_types=1);

namespace App\Application\Cloud\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

final readonly class CloudServerTerminated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public int $userId,
        public int $serverId,
        public string $serverName,
        public string $expiresAt,
        public string $terminatedAt,
    ) {}

    public function dedupeKey(): string
    {
        return sprintf(
            'cloud-server:%d:terminated:%s',
            $this->serverId,
            sha1(
                $this->expiresAt,
            ),
        );
    }
}
