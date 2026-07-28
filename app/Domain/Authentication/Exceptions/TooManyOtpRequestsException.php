<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Exceptions;

use RuntimeException;

final class TooManyOtpRequestsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'Too many OTP requests.',
        );
    }
}
