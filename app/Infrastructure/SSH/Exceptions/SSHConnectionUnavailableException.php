<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Exceptions;

final class SSHConnectionUnavailableException extends SSHConnectionException
{
    public function __construct(
        private readonly int $retryAfterSeconds,
    ) {
        parent::__construct(
            sprintf(
                'SSH connection is temporarily unavailable. Retry after %d seconds.',
                $this->retryAfterSeconds,
            ),
        );
    }

    public static function retryAfter(int $seconds): self
    {
        return new self(
            max(1, $seconds),
        );
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
