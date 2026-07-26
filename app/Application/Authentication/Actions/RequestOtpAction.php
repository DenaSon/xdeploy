<?php

declare(strict_types=1);

namespace App\Application\Authentication\Actions;

use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\Authentication\Services\OtpService;
use App\Infrastructure\Sms\Services\SmsService;

final readonly class RequestOtpAction
{
    public function __construct(
        private OtpService $otpService,
        private SmsService $smsService,
    ) {}

    public function handle(
        RequestOtpData $data,
    ): void {
        $code = $this->otpService->generate(
            $data->phone,
        );

        $this->smsService->sendVerificationCode(
            phone: $data->phone,
            code: $code,
        );
    }
}
