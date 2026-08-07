<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Exceptions;

use RuntimeException;

final class SSHCommandUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'SSH authentication succeeded but command execution is unavailable.',
        );
    }
}
