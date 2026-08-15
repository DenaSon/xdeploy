<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Exceptions;

use DomainException;

final class CannotDeleteLastAdminPasskeyException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'The final administrator passkey cannot be deleted.',
        );
    }
}
