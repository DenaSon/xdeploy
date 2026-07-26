<?php

namespace App\Application\Server\Actions;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Models\Server;

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
