<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\Authentication\Services\OtpService;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Infrastructure\Sms\Exceptions\SmsSendingException;
use App\Infrastructure\Sms\Services\SmsService;

final readonly class RequestOtpAction
{
    public function __construct(
        private OtpService $otpService,
        private SmsService $smsService,
    ) {}

    public function handle(
        RequestOtpData $data,
    ): ?OtpCode {
        $code = $this->otpService->generate(
            $data->phone,
        );

        try {
            $this->smsService->sendVerificationCode(
                phone: $data->phone,
                code: $code,
            );
        } catch (SmsSendingException $exception) {
            if (! $this->browserConsoleDebugEnabled()) {
                throw $exception;
            }
        }

        if (! $this->browserConsoleDebugEnabled()) {
            return null;
        }

        return $code;
    }

    private function browserConsoleDebugEnabled(): bool
    {
        return (bool) config(
            'services.sms.otp_browser_console_debug',
            false,
        );
    }
}
