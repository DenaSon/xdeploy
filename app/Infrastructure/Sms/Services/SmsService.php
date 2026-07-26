<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms\Services;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;

final readonly class SmsService
{
    public function __construct(
        private SmsProviderInterface $provider,
    ) {}

    public function sendVerificationCode(
        PhoneNumber $phone,
        OtpCode $code,
    ): void {
        $this->provider->sendVerificationCode(
            $phone,
            $code,
        );
    }
}
