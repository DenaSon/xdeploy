<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHPortReadinessProbeInterface;
use App\Infrastructure\SSH\Security\SSHConnectionTargetPolicy;
use App\Models\Server;
use InvalidArgumentException;

final readonly class SSHPortReadinessProbe implements SSHPortReadinessProbeInterface
{
    private const int MAX_WAIT_SECONDS = 120;

    private const int POLL_INTERVAL_SECONDS = 5;

    private const int CONNECT_TIMEOUT_SECONDS = 2;

    public function __construct(
        private SSHConnectionTargetPolicy $targetPolicy,
    ) {}

    public function waitUntilReady(
        Server $server,
    ): bool {
        if (! $server->hasConnectionHost()) {
            return false;
        }

        $host = $this->targetPolicy->resolve(
            $server->host,
        );

        $port = $server->port;

        if ($port < 1 || $port > 65_535) {
            throw new InvalidArgumentException(
                'SSH port is invalid.',
            );
        }

        $startedAt = microtime(true);

        $deadline = $startedAt
            + self::MAX_WAIT_SECONDS;

        $attempt = 0;

        do {
            $attempt++;

            if (
                $this->canConnect(
                    host: $host,
                    port: $port,
                )
            ) {
                logger()->info(
                    'ssh.port.ready',
                    [
                        'server_id' => $server->getKey(),
                        'attempt' => $attempt,
                        'duration_ms' => $this
                            ->durationInMilliseconds(
                                $startedAt,
                            ),
                    ],
                );

                return true;
            }

            logger()->debug(
                'ssh.port.not_ready',
                [
                    'server_id' => $server->getKey(),
                    'attempt' => $attempt,
                    'duration_ms' => $this
                        ->durationInMilliseconds(
                            $startedAt,
                        ),
                ],
            );

            if (
                microtime(true)
                >= $deadline
            ) {
                break;
            }

            sleep(
                self::POLL_INTERVAL_SECONDS,
            );
        } while (
            microtime(true)
            < $deadline
        );

        logger()->warning(
            'ssh.port.readiness_timeout',
            [
                'server_id' => $server->getKey(),
                'attempts' => $attempt,
                'timeout_seconds' => self::MAX_WAIT_SECONDS,
                'duration_ms' => $this
                    ->durationInMilliseconds(
                        $startedAt,
                    ),
            ],
        );

        return false;
    }

    private function canConnect(
        string $host,
        int $port,
    ): bool {
        $errorCode = 0;
        $errorMessage = '';

        $socket = @fsockopen(
            $host,
            $port,
            $errorCode,
            $errorMessage,
            self::CONNECT_TIMEOUT_SECONDS,
        );

        if ($socket === false) {
            return false;
        }

        fclose(
            $socket,
        );

        return true;
    }

    private function durationInMilliseconds(
        float $startedAt,
    ): int {
        return (int) round(
            (microtime(true) - $startedAt)
            * 1_000,
        );
    }
}
