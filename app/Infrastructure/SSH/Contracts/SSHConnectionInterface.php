<?php

namespace App\Infrastructure\SSH\Contracts;
use App\Infrastructure\SSH\DTOs\SSHResult;
interface SSHConnectionInterface
{
    public function connect(
        string $host,
        int $port,
        string $username,
        string $authenticationType,
        ?string $credential = null,
        ?string $privateKeyPath = null
    ): bool;

    public function execute(string $command): string;
    public function executeWithResult(string $command): SSHResult;

    public function isConnected(): bool;

    public function disconnect(): void;
}
