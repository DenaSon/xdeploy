<?php

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

class SSHConnection implements SSHConnectionInterface
{
    public function connect(
        string $host,
        int $port,
        string $username,
        string $authenticationType,
        ?string $credential = null,
        ?string $privateKeyPath = null
    ): bool {
        return false;
    }

    public function execute(string $command): string
    {
        return '';
    }

    public function disconnect(): void {}
}
