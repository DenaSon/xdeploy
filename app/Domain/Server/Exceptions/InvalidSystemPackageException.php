<?php

declare(strict_types=1);

namespace App\Domain\Server\Exceptions;

use InvalidArgumentException;

final class InvalidSystemPackageException extends InvalidArgumentException
{
    public function __construct(
        string $package,
    ) {
        parent::__construct(
            sprintf(
                'Invalid system package name [%s].',
                $package,
            ),
        );
    }
}
