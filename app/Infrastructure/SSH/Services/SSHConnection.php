<?php

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class SSHConnection implements SSHConnectionInterface
{
    private ?SSH2 $ssh = null;

    public function connect(
        string $host,
        int $port,
        string $username,
        string $authenticationType,
        ?string $credential = null,
        ?string $privateKeyPath = null
    ): bool {

        $this->ssh = new SSH2($host, $port);

        switch ($authenticationType) {

            case 'password':

                if (! $this->ssh->login($username, $credential)) {
                    return false;
                }

                break;

            case 'private_key':

                if (! $privateKeyPath || ! file_exists($privateKeyPath)) {
                    throw new SSHConnectionException('Private key not found.');
                }

                $key = PublicKeyLoader::load(file_get_contents($privateKeyPath));

                if (! $this->ssh->login($username, $key)) {
                    return false;
                }

                break;

            default:

                throw new SSHConnectionException(
                    "Unsupported authentication type: {$authenticationType}"
                );
        }

        return true;
    }

    public function execute(string $command): string
    {
        if (! $this->ssh) {
            throw new SSHConnectionException('SSH connection is not established.');
        }

        return $this->ssh->exec($command);
    }

    public function disconnect(): void
    {
        $this->ssh = null;
    }
}
