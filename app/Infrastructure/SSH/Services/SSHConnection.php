<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Authentication\AuthenticationStrategyFactory;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use phpseclib3\Exception\ConnectionClosedException;
use phpseclib3\Net\SSH2;

class SSHConnection implements SSHConnectionInterface
{
    private ?SSH2 $ssh = null;

    private ?Server $server = null;

    public function __construct(
        private readonly AuthenticationStrategyFactory $authenticationStrategyFactory,
    ) {}

    public function connect(Server $server): bool
    {
        $this->disconnect();

        $this->server = $server;

        $this->ssh = new SSH2(
            $server->host,
            $server->port,
        );
        $this->ssh->setTimeout(40);

        $strategy = $this->authenticationStrategyFactory->make(
            $server->authentication_type,
        );

        $authenticated = $strategy->authenticate(
            $this->ssh,
            $server,
        );

        if (! $authenticated) {
            $this->disconnect();

            return false;
        }

        return true;
    }

    public function execute(string $command): string
    {
        $this->ensureConnection();

        try {
            return $this->ssh->exec(
                $this->normalizeCommand($command)
            );
        } catch (ConnectionClosedException) {
            if (! $this->reconnect()) {
                throw new SSHConnectionException(
                    'Unable to re-establish SSH connection.'
                );
            }

            return $this->ssh->exec(
                $this->normalizeCommand($command)
            );
        }
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): SSHResult {
        $this->ensureConnection();

        $command = $this->normalizeCommand($command);

        $this->ssh->setTimeout($timeout);

        try {
            $startedAt = microtime(true);

            $output = $this->ssh->exec($command);

            logger()->debug('SSH Command Output', [
                'length' => strlen((string) $output),
            ]);

            $timedOut = $this->ssh->isTimeout();

            if ($timedOut) {
                $this->handleTimeout($command);
            }

            $result = new SSHResult(
                output: is_string($output)
                    ? trim($output)
                    : '',
                exitCode: $this->ssh->getExitStatus() ?? -1,
            );

            logger()->info('SSH Command Executed', [
                'command' => $command,
                'duration' => round(microtime(true) - $startedAt, 3),
                'exit_code' => $result->exitCode,
                'timeout' => $timedOut,
                'output_length' => strlen($result->output),
            ]);

            return $result;
        } finally {
            if ($this->ssh !== null) {
                $this->ssh->setTimeout(
                    SSHTimeout::DEFAULT
                );
            }
        }
    }

    private function normalizeCommand(
        string $command,
    ): string {
        return preg_replace(
            '/\r\n?/',
            "\n",
            trim($command),
        );
    }

    public function isConnected(): bool
    {
        return $this->ssh !== null
            && $this->ssh->isAuthenticated();
    }

    public function disconnect(): void
    {
        if ($this->ssh !== null) {
            $this->ssh->disconnect();
        }

        $this->ssh = null;
    }

    private function ensureConnection(): void
    {
        if (! $this->isConnected() && ! $this->reconnect()) {
            throw new SSHConnectionException(
                'Unable to establish SSH connection.'
            );
        }
    }

    private function handleTimeout(string $command): void
    {
        logger()->warning('SSH command timed out.', [
            'command' => $command,
        ]);

        try {
            $this->ssh->reset();
        } catch (\Throwable) {
            $this->disconnect();

            if (! $this->reconnect()) {
                throw new SSHConnectionException(
                    'Unable to reconnect after SSH timeout.'
                );
            }
        }
    }

    private function reconnect(): bool
    {
        if ($this->server === null) {
            return false;
        }

        return $this->connect($this->server);
    }
}
