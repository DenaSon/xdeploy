<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Exceptions;

use RuntimeException;

final class OtpExpiredException extends RuntimeException
{
    protected $message = 'کد تأیید منقضی شده است. لطفاً دوباره درخواست کد دهید.';
}
