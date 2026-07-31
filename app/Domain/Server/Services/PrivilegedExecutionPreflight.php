<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

final readonly class PrivilegedExecutionPreflight
{
    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    /**
     * Ensure the current SSH session has root privileges.
     */
    public function ensureRoot(): void
    {
        if (! $this->isRoot()) {
            throw new RootPrivilegesRequiredException;
        }
    }

    /**
     * Determine whether the current SSH session belongs to root.
     */
    public function isRoot(): bool
    {
        $result = $this->ssh->executeWithResult(
            'id -u',
        );

        return $result->successful()
            && trim($result->output) === '0';
    }
}
