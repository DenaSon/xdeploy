<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Support\SSH\SSHTimeout;

final readonly class PrivilegedCommandExecutor
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedExecutionPreflight $preflight,
    ) {}

    public function execute(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): string {
        $mode = $this->preflight->detect();

        return $this->ssh->execute(
            command: $mode->wrapCommand($command),
            timeout: $timeout,
        );
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult {
        $mode = $this->preflight->detect();

        return $this->ssh->executeWithResult(
            command: $mode->wrapCommand($command),
            timeout: $timeout,
            sensitive: $sensitive,
        );
    }
}
