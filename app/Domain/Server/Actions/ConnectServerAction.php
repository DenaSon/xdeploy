<?php

namespace App\Domain\Server\Actions;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

readonly class ConnectServerAction
{
    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function handle(
        string $host,
        int $port,
        string $username,
        string $authenticationType,
        ?string $credential = null,
        ?string $privateKeyPath = null,
    ): bool {
        return $this->ssh->connect(
            host: $host,
            port: $port,
            username: $username,
            authenticationType: $authenticationType,
            credential: $credential,
            privateKeyPath: $privateKeyPath,
        );
    }
}
