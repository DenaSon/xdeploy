<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Exceptions;

use RuntimeException;

final class TooManyOtpAttemptsException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct(
            'تعداد تلاش‌های وارد کردن کد تأیید بیش از حد مجاز است.',
        );
    }
}
