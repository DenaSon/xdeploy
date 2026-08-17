<?php

declare(strict_types=1);

namespace App\Domain\Server\Exceptions;

use RuntimeException;

final class SystemPackageManagerBusyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'The system package manager remained busy beyond the retry budget.',
        );
    }
}
