<?php

namespace App\Infrastructure\SSH\Contracts;

use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Models\Server;

interface SSHConnectionInterface
{
    public function connect(Server $server): bool;

    public function execute(string $command): string;

    public function executeWithResult(string $command): SSHResult;

    public function isConnected(): bool;

    public function disconnect(): void;
}
