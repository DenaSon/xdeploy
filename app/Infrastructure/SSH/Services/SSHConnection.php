<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Authentication\AuthenticationStrategyFactory;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\DTOs\SSHResult;
use App\Infrastructure\SSH\Exceptions\SSHCommandTimeoutException;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Infrastructure\SSH\Exceptions\SSHConnectionUnavailableException;
use App\Infrastructure\SSH\Security\SSHConnectionTargetPolicy;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use Illuminate\Support\Str;
use InvalidArgumentException;
use phpseclib3\Exception\ConnectionClosedException;
use phpseclib3\Net\SSH2;
use Throwable;

final class SSHConnection implements SSHConnectionInterface
{
    private const int OUTPUT_EXCERPT_LENGTH = 1_000;

    private ?SSH2 $ssh = null;

    private ?Server $server = null;

    public function __construct(
        private readonly AuthenticationStrategyFactory $authenticationStrategyFactory,
        private readonly SSHConnectionCircuitBreaker $circuitBreaker,
        private readonly SSHConnectionTargetPolicy $targetPolicy,
    ) {}

    public function connect(Server $server): bool
    {
        $resolvedHost = $this->targetPolicy->resolve(
            $server->host,
        );
        $strategy = $this->authenticationStrategyFactory->make(
            $server->authentication_type,
        );
        $this->disconnect();

        $this->server = $server;

        /*
         * When the circuit is open, this throws immediately and prevents
         * another expensive SSH authentication attempt.
         */
        $this->guardConnection($server);

        $connectionId = (string) Str::uuid();
        $startedAt = microtime(true);

        logger()->info('ssh.connection.started', [
            'connection_id' => $connectionId,
            'server_id' => $server->getKey(),
            'port' => $server->port,
            'timeout_seconds' => SSHTimeout::CONNECTION,
        ]);

        try {
            $this->ssh = new SSH2(
                host: $resolvedHost,
                port: $server->port,
                timeout: SSHTimeout::CONNECTION,
            );

            $this->ssh->setTimeout(
                SSHTimeout::AUTHENTICATION,
            );

            $authenticated = $strategy->authenticate(
                ssh: $this->ssh,
                server: $server,
            );

            if (! $authenticated) {
                $this->recordConnectionFailure($server);

                logger()->warning('ssh.connection.authentication_failed', [
                    'connection_id' => $connectionId,
                    'server_id' => $server->getKey(),
                    'duration_ms' => $this->durationInMilliseconds(
                        $startedAt,
                    ),
                ]);

                $this->disconnect();

                return false;
            }

            $this->configureAuthenticatedTransport($this->ssh);

            $this->recordConnectionSuccess($server);

            logger()->info('ssh.connection.completed', [
                'connection_id' => $connectionId,
                'server_id' => $server->getKey(),
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->recordConnectionFailure($server);

            logger()->error('ssh.connection.failed', [
                'connection_id' => $connectionId,
                'server_id' => $server->getKey(),
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
                'exception_type' => $exception::class,
            ]);

            $this->disconnect();

            return false;
        }
    }

    public function execute(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
    ): string {
        return $this->executeWithResult(
            command: $command,
            timeout: $timeout,
        )->output;
    }

    public function executeWithResult(
        string $command,
        int $timeout = SSHTimeout::DEFAULT,
        bool $sensitive = false,
    ): SSHResult {
        $this->ensureConnection();

        $command = $this->normalizeCommand($command);

        $executionId = (string) Str::uuid();
        $commandHash = hash('sha256', $command);
        $startedAt = microtime(true);

        $this->ssh->setTimeout($timeout);

        logger()->info('ssh.command.started', [
            'execution_id' => $executionId,
            'server_id' => $this->server?->getKey(),
            'command_hash' => $commandHash,
            'command_length' => strlen($command),
            'command' => app()->environment('local')
                ? $command
                : '[hidden]',
            'timeout_seconds' => $timeout,
            'sensitive' => $sensitive,
        ]);

        try {
            $output = $this->ssh->exec($command);

            if ($this->ssh->isTimeout()) {
                $this->handleTimeout(
                    executionId: $executionId,
                    commandHash: $commandHash,
                    timeout: $timeout,
                    startedAt: $startedAt,
                );
            }

            $result = new SSHResult(
                output: is_string($output)
                    ? trim($output)
                    : '',
                exitCode: $this->resolveExitCode(),
            );

            $context = [
                'execution_id' => $executionId,
                'server_id' => $this->server?->getKey(),
                'command_hash' => $commandHash,
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
                'exit_code' => $result->exitCode,
                'output_length' => strlen($result->output),
                'successful' => $result->successful(),
                'sensitive' => $sensitive,
            ];

            if (
                ! $result->successful()
                && ! $sensitive
                && $result->output !== ''
            ) {
                $context['output_excerpt'] = $this->outputExcerpt(
                    $result->output,
                );
            }

            logger()->log(
                $result->successful() ? 'info' : 'warning',
                'ssh.command.completed',
                $context,
            );

            return $result;
        } catch (SSHCommandTimeoutException $exception) {
            throw $exception;
        } catch (ConnectionClosedException $exception) {
            /*
             * A closed connection during command execution is also an
             * availability signal. It counts as one circuit failure.
             */
            if ($this->server !== null) {
                $this->recordConnectionFailure(
                    $this->server,
                );
            }

            logger()->error('ssh.command.connection_closed', [
                'execution_id' => $executionId,
                'server_id' => $this->server?->getKey(),
                'command_hash' => $commandHash,
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
                'exception_type' => $exception::class,
                'sensitive' => $sensitive,
            ]);

            $this->disconnect();

            throw new SSHConnectionException(
                'SSH connection closed during command execution.',
            );
        } catch (Throwable $exception) {
            logger()->error('ssh.command.failed', [
                'execution_id' => $executionId,
                'server_id' => $this->server?->getKey(),
                'command_hash' => $commandHash,
                'duration_ms' => $this->durationInMilliseconds(
                    $startedAt,
                ),
                'exception_type' => $exception::class,
                'sensitive' => $sensitive,
            ]);

            $this->disconnect();

            throw new SSHConnectionException(
                'SSH command execution failed.',
            );
        } finally {
            $this->restoreDefaultTimeout();
        }
    }

    public function isConnected(): bool
    {
        try {
            return $this->ssh !== null
                && $this->ssh->isAuthenticated();
        } catch (Throwable) {
            return false;
        }
    }

    public function disconnect(): void
    {
        $ssh = $this->ssh;

        /*
         * Clear all connection-scoped state first.
         *
         * Even if closing the underlying transport fails,
         * this object must no longer represent a server session.
         */
        $this->ssh = null;
        $this->server = null;

        if ($ssh === null) {
            return;
        }

        try {
            $ssh->disconnect();
        } catch (Throwable) {
            /*
             * Connection cleanup must never hide
             * the original exception.
             */
        }
    }

    private function configureAuthenticatedTransport(SSH2 $ssh): void
    {
        $ssh->setKeepAlive(
            SSHTimeout::KEEPALIVE,
        );

        $ssh->setTimeout(
            SSHTimeout::DEFAULT,
        );
    }

    private function ensureConnection(): void
    {
        if ($this->isConnected()) {
            return;
        }

        if (! $this->reconnect()) {
            throw new SSHConnectionException(
                'Unable to establish SSH connection.',
            );
        }
    }

    private function reconnect(): bool
    {
        if ($this->server === null) {
            return false;
        }

        return $this->connect(
            $this->server,
        );
    }

    private function guardConnection(Server $server): void
    {
        try {
            $this->circuitBreaker->guard($server);
        } catch (SSHConnectionUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            /*
             * Circuit-breaker storage problems must not prevent a real SSH
             * connection attempt. The circuit therefore fails open.
             */
            logger()->warning('ssh.circuit.guard_failed', [
                'server_id' => $server->getKey(),
                'exception_type' => $exception::class,
            ]);
        }
    }

    private function recordConnectionFailure(
        Server $server,
    ): void {
        try {
            $this->circuitBreaker->recordFailure(
                $server,
            );
        } catch (Throwable $exception) {
            logger()->warning('ssh.circuit.failure_record_failed', [
                'server_id' => $server->getKey(),
                'exception_type' => $exception::class,
            ]);
        }
    }

    private function recordConnectionSuccess(
        Server $server,
    ): void {
        try {
            $this->circuitBreaker->recordSuccess(
                $server,
            );
        } catch (Throwable $exception) {
            logger()->warning('ssh.circuit.success_record_failed', [
                'server_id' => $server->getKey(),
                'exception_type' => $exception::class,
            ]);
        }
    }

    private function handleTimeout(
        string $executionId,
        string $commandHash,
        int $timeout,
        float $startedAt,
    ): never {
        logger()->warning('ssh.command.timed_out', [
            'execution_id' => $executionId,
            'server_id' => $this->server?->getKey(),
            'command_hash' => $commandHash,
            'duration_ms' => $this->durationInMilliseconds(
                $startedAt,
            ),
            'timeout_seconds' => $timeout,
        ]);

        /*
         * A timed-out command may already have changed the remote server.
         * Disconnect instead of attempting to execute it again.
         */
        $this->disconnect();

        throw SSHCommandTimeoutException::after(
            $timeout,
        );
    }

    private function restoreDefaultTimeout(): void
    {
        if ($this->ssh === null) {
            return;
        }

        try {
            $this->ssh->setTimeout(
                SSHTimeout::DEFAULT,
            );
        } catch (Throwable) {
            $this->disconnect();
        }
    }

    private function normalizeCommand(
        string $command,
    ): string {
        $command = str_replace(
            ["\r\n", "\r"],
            "\n",
            trim($command),
        );

        if ($command === '') {
            throw new InvalidArgumentException(
                'SSH command cannot be empty.',
            );
        }

        return $command;
    }

    private function resolveExitCode(): int
    {
        $exitCode = $this->ssh?->getExitStatus();

        return is_int($exitCode)
            ? $exitCode
            : -1;
    }

    private function durationInMilliseconds(
        float $startedAt,
    ): int {
        return (int) round(
            (microtime(true) - $startedAt) * 1_000,
        );
    }

    private function outputExcerpt(
        string $output,
    ): string {
        $output = preg_replace(
            '/\e\[[0-9;?]*[ -\/]*[@-~]/',
            '',
            $output,
        ) ?? $output;

        $output = preg_replace(
            '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',
            '',
            $output,
        ) ?? $output;

        return mb_substr(
            trim($output),
            0,
            self::OUTPUT_EXCERPT_LENGTH,
        );
    }
}
