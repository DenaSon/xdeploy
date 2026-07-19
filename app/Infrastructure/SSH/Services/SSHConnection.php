<?php

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;

class SSHConnection implements SSHConnectionInterface
{
    private ?SSH2 $ssh = null;

    private ?string $host = null;

    private ?int $port = null;

    private ?string $username = null;

    private ?string $authenticationType = null;

    private ?string $credential = null;

    private ?string $privateKeyPath = null;

    public function connect(
        string $host,
        int $port,
        string $username,
        string $authenticationType,
        ?string $credential = null,
        ?string $privateKeyPath = null
    ): bool {

        $this->disconnect();

        $this->rememberConnection(
            $host,
            $port,
            $username,
            $authenticationType,
            $credential,
            $privateKeyPath
        );

        $this->ssh = new SSH2($host, $port);

        $authenticated = match ($authenticationType) {

            'password' => $this->ssh->login(
                $username,
                $credential
            ),

            'private_key' => $this->loginWithPrivateKey(
                $username,
                $privateKeyPath
            ),

            default => throw new SSHConnectionException(
                "Unsupported authentication type: {$authenticationType}"
            ),
        };

        if (! $authenticated) {
            $this->disconnect();

            return false;
        }

        return true;
    }

    public function execute(string $command): string
    {
        if (! $this->isConnected() && ! $this->reconnect()) {
            throw new SSHConnectionException(
                'Unable to establish SSH connection.'
            );
        }

        return $this->ssh->exec($command);
    }

    public function isConnected(): bool
    {
        return $this->ssh !== null;
    }

    public function disconnect(): void
    {
        if ($this->ssh !== null) {
            $this->ssh->disconnect();
        }

        $this->ssh = null;
    }

    private function reconnect(): bool
    {
        if (
            $this->host === null ||
            $this->port === null ||
            $this->username === null ||
            $this->authenticationType === null
        ) {
            return false;
        }

        return $this->connect(
            $this->host,
            $this->port,
            $this->username,
            $this->authenticationType,
            $this->credential,
            $this->privateKeyPath
        );
    }

    private function rememberConnection(
        string $host,
        int $port,
        string $username,
        string $authenticationType,
        ?string $credential,
        ?string $privateKeyPath
    ): void {

        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->authenticationType = $authenticationType;
        $this->credential = $credential;
        $this->privateKeyPath = $privateKeyPath;
    }

    private function loginWithPrivateKey(
        string $username,
        ?string $privateKeyPath
    ): bool {

        if (! $privateKeyPath || ! file_exists($privateKeyPath)) {
            throw new SSHConnectionException(
                'Private key not found.'
            );
        }

        $key = PublicKeyLoader::load(
            file_get_contents($privateKeyPath)
        );

        return $this->ssh->login($username, $key);
    }
}
