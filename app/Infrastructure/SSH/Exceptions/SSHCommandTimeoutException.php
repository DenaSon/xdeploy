<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Exceptions;

final class SSHCommandTimeoutException extends SSHConnectionException
{
    public static function after(int $seconds): self
    {
        return new self(
            sprintf(
                'SSH command timed out after %d seconds.',
                $seconds,
            ),
        );
    }
}
