<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms\Contracts;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;

interface SmsProviderInterface
{
    /**
     * Send an OTP verification code to the given phone number.
     */
    public function sendVerificationCode(
        PhoneNumber $phone,
        OtpCode $code,
    ): void;
}
