<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Authentication;

use App\Domain\Server\Models\Server;
use phpseclib3\Net\SSH2;

final class AgentAuthenticator implements AuthenticationStrategy
{
    public function authenticate(SSH2 $ssh, Server $server): bool
    {
        // TODO:
        // Authenticate using the local SSH agent.

        throw new \LogicException(
            'SSH agent authentication is not implemented yet.'
        );
    }
}
