<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\Enums\PrivilegedExecutionMode;
use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class PrivilegedExecutionPreflight
{
    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function detect(): PrivilegedExecutionMode
    {
        if ($this->isRoot()) {
            return PrivilegedExecutionMode::Root;
        }

        if ($this->hasPasswordlessSudo()) {
            return PrivilegedExecutionMode::PasswordlessSudo;
        }

        throw new RootPrivilegesRequiredException;
    }

    /**
     * Backward-compatible method for existing workflows.
     */
    public function ensureRoot(): void
    {
        $this->detect();
    }

    public function isRoot(): bool
    {
        $result = $this->ssh->executeWithResult(
            'id -u',
            SSHTimeout::QUICK,
        );

        return $result->successful()
            && trim($result->output) === '0';
    }

    public function hasPasswordlessSudo(): bool
    {
        $result = $this->ssh->executeWithResult(
            'sudo -n id -u',
            SSHTimeout::QUICK,
        );

        return $result->successful()
            && trim($result->output) === '0';
    }
}
