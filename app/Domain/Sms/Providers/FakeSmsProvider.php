<?php

declare(strict_types=1);

namespace App\Domain\SMS\Providers;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\SMS\Contracts\SmsProviderInterface;
use App\Domain\User\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Log;

final readonly class FakeSmsProvider implements SmsProviderInterface
{
    public function sendVerificationCode(
        PhoneNumber $phone,
        OtpCode $code,
    ): void {
        Log::info('Fake SMS sent.', [
            'phone' => (string) $phone,
            'code' => (string) $code,
        ]);
    }
}
