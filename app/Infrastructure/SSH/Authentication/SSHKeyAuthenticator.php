<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Authentication;

use App\Models\Server;
use phpseclib3\Net\SSH2;

class SSHKeyAuthenticator implements AuthenticationStrategy
{
    public function authenticate(
        SSH2 $ssh,
        Server $server,
    ): bool {
        throw new \LogicException(
            'SSH Key authentication is not implemented.'
        );
    }
}
