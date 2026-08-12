<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms\Services;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use Log;

final readonly class SmsService
{
    public function __construct(
        private SmsProviderInterface $provider,
    ) {}

    public function sendVerificationCode(
        PhoneNumber $phone,
        OtpCode $code,
    ): void {
        if (! app()->environment('local')) {
            return;
        }

        Log::info('Fake SMS sent.', [
            'phone' => (string) $phone,
            'type' => 'verification_code',
            'code' => (string) $code,
        ]);
    }

    public function sendCloudServerExpirationWarning(
        PhoneNumber $phone,
    ): void {
        $this->provider->sendCloudServerExpirationWarning(
            $phone,
        );
    }
}
