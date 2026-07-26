<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Exceptions;

use RuntimeException;

final class OtpExpiredException extends RuntimeException
{
    protected $message = 'The verification code has expired.';
}
