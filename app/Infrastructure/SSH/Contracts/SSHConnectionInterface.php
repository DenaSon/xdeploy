<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Contracts;

use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;

interface SSHConnectionInterface
{
    public function connect(Server $server): bool;

    public function execute(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): string;

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult;

    public function isConnected(): bool;

    public function disconnect(): void;
}
