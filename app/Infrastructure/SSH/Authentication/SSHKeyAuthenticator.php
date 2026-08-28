<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Authentication;

use App\Models\Server;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

final class SSHKeyAuthenticator implements AuthenticationStrategy
{
    public function authenticate(
        SSH2 $ssh,
        Server $server,
    ): bool {
        $privateKey = $server->credential;

        if (! is_string($privateKey) || trim($privateKey) === '') {
            return false;
        }

        $key = PublicKeyLoader::loadPrivateKey(
            $privateKey,
        );

        return $ssh->login(
            $server->username,
            $key,
        );
    }
}
