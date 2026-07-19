<?php

declare(strict_types=1);

namespace App\Domain\Server\Exceptions;

use InvalidArgumentException;

final class UnsupportedCommandException extends InvalidArgumentException
{
    public function __construct(string $command)
    {
        parent::__construct(
            "Command [{$command}] is not supported."
        );
    }
}
