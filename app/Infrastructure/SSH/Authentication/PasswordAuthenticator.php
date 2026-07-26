<?php

namespace App\Infrastructure\SSH\Authentication;

use App\Domain\Server\Models\Server;
use phpseclib3\Net\SSH2;

class PasswordAuthenticator implements AuthenticationStrategy
{
    public function authenticate(
        SSH2 $ssh,
        Server $server,
    ): bool {
        return $ssh->login(
            $server->username,
            $server->credential,
        );
    }
}
