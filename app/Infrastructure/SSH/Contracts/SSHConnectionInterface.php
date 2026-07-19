<?php

namespace App\Infrastructure\SSH\Contracts;

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

    public function isConnected(): bool;

    public function disconnect(): void;
}
