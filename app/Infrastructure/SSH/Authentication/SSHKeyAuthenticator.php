<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Authentication;

use phpseclib3\Net\SSH2;
use App\Domain\Server\Models\Server;

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
