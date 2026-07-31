<?php

declare(strict_types=1);

namespace App\Domain\Server\Exceptions;

use RuntimeException;

final class RootPrivilegesRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Root privileges are required for this operation.',
        );
    }
}
