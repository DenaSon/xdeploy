<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Exceptions;

use RuntimeException;

final class SSHPasswordChangeRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'SSH password change is required before commands can be executed.',
        );
    }
}
