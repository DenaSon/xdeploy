<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Exceptions\SSHConnectionUnavailableException;
use App\Models\Server;
use Illuminate\Cache\RateLimiter;

final readonly class SSHConnectionCircuitBreaker
{
    private const MAX_FAILURES = 2;

    private const COOLDOWN_SECONDS = 60;

    private const KEY_PREFIX = 'ssh:connection-circuit:';

    public function __construct(
        private RateLimiter $limiter,
    ) {}

    /**
     * Prevent a new SSH connection while the circuit is open.
     */
    public function guard(Server $server): void
    {
        if (! $this->isOpen($server)) {
            return;
        }

        $retryAfter = $this->retryAfter($server);

        logger()->debug('ssh.connection.skipped', [
            'server_id' => $server->getKey(),
            'failures' => $this->failures($server),
            'retry_after_seconds' => $retryAfter,
        ]);

        throw SSHConnectionUnavailableException::retryAfter(
            $retryAfter,
        );
    }

    /**
     * Record one failed SSH connection attempt.
     */
    public function recordFailure(Server $server): void
    {
        $key = $this->key($server);

        $failures = $this->limiter->hit(
            key: $key,
            decaySeconds: self::COOLDOWN_SECONDS,
        );

        if ($failures < self::MAX_FAILURES) {
            logger()->warning('ssh.connection.failure_recorded', [
                'server_id' => $server->getKey(),
                'failures' => $failures,
                'max_failures' => self::MAX_FAILURES,
            ]);

            return;
        }

        /*
         * Restart the cooldown from the moment the circuit opens.
         *
         * Without this reset, the limiter expiration would be calculated
         * from the first failure instead of the threshold failure.
         */
        $this->limiter->clear($key);

        $this->limiter->increment(
            key: $key,
            decaySeconds: self::COOLDOWN_SECONDS,
            amount: self::MAX_FAILURES,
        );

        logger()->warning('ssh.circuit.opened', [
            'server_id' => $server->getKey(),
            'failures' => self::MAX_FAILURES,
            'retry_after_seconds' => self::COOLDOWN_SECONDS,
        ]);
    }

    /**
     * Reset the circuit after a successful SSH connection.
     */
    public function recordSuccess(Server $server): void
    {
        $key = $this->key($server);
        $failures = $this->failures($server);

        if ($failures === 0) {
            return;
        }

        $wasOpen = $this->isOpen($server);

        $this->limiter->clear($key);

        logger()->info(
            $wasOpen
                ? 'ssh.circuit.closed'
                : 'ssh.connection.failures_cleared',
            [
                'server_id' => $server->getKey(),
                'previous_failures' => $failures,
            ],
        );
    }

    public function isOpen(Server $server): bool
    {
        return $this->limiter->tooManyAttempts(
            key: $this->key($server),
            maxAttempts: self::MAX_FAILURES,
        );
    }

    public function retryAfter(Server $server): int
    {
        if (! $this->isOpen($server)) {
            return 0;
        }

        return max(
            1,
            $this->limiter->availableIn(
                $this->key($server),
            ),
        );
    }

    public function failures(Server $server): int
    {
        return max(
            0,
            (int) $this->limiter->attempts(
                $this->key($server),
            ),
        );
    }

    /**
     * Manually clear the circuit, for example when the user presses Retry.
     */
    public function reset(Server $server): void
    {
        $key = $this->key($server);
        $failures = $this->failures($server);

        $this->limiter->clear($key);

        logger()->info('ssh.circuit.reset', [
            'server_id' => $server->getKey(),
            'previous_failures' => $failures,
        ]);
    }

    private function key(Server $server): string
    {
        $serverId = $server->getKey();

        if ($serverId !== null) {
            return self::KEY_PREFIX.'server:'.$serverId;
        }

        return self::KEY_PREFIX.'connection:'.hash(
            'sha256',
            implode(':', [
                $server->host,
                (string) $server->port,
                $server->username,
            ]),
        );
    }
}
