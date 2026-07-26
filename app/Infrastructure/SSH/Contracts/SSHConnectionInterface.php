<?php

namespace App\Infrastructure\SSH\Contracts;

use App\Domain\Server\Models\Server;
use App\Infrastructure\SSH\DTOs\SSHResult;

interface SSHConnectionInterface
{
    public function connect(Server $server): bool;

    public function execute(string $command): string;

    public function executeWithResult(string $command): SSHResult;

    public function isConnected(): bool;

    public function disconnect(): void;
}
