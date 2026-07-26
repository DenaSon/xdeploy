<?php

namespace App\Domain\Server\Actions;

use App\Domain\Server\Models\Server;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

readonly class ConnectServerAction
{
    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function handle(Server $server): bool
    {
        return $this->ssh->connect($server);
    }
}
