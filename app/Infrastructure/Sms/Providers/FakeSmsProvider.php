<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms\Providers;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

final readonly class FakeSmsProvider implements SmsProviderInterface
{
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
        if (! app()->environment('local')) {
            return;
        }

        Log::info('Fake SMS sent.', [
            'phone' => (string) $phone,
            'type' => 'cloud_server_expiring_soon',
        ]);
    }
}
