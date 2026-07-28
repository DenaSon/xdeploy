<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Exceptions;

use RuntimeException;

final class TooManyOtpRequestsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(
            'تعداد درخواست‌های دریافت کد تأیید بیش از حد مجاز است. لطفاً چند دقیقه دیگر دوباره تلاش کنید.',
        );
    }
}
