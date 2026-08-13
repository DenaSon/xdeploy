<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations\Exceptions;

use RuntimeException;

final class ApplicationOperationInProgressException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Another application operation is already in progress for this target.',
        );
    }
}
