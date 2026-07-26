<?php

namespace App\Infrastructure\SSH\Authentication;

use App\Models\Server;
use phpseclib3\Net\SSH2;

interface AuthenticationStrategy
{
    public function authenticate(
        SSH2 $ssh,
        Server $server,
    ): bool;
}
