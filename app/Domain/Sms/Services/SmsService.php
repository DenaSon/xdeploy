<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\SMS\Contracts\SmsProviderInterface;
use App\Domain\User\ValueObjects\PhoneNumber;

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
