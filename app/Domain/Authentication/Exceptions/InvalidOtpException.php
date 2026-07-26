<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Exceptions;

use RuntimeException;

final class InvalidOtpException extends RuntimeException
{
    protected $message = 'The verification code is invalid.';
}
