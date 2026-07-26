<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms\Providers;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use Ipe\Sdk\Facades\SmsIr;
use Throwable;

final readonly class SmsIrProvider implements SmsProviderInterface
{
    public function __construct(
        private int $templateId,
        private string $parameterName = 'Code',
    ) {}

    public function sendVerificationCode(
        PhoneNumber $phone,
        OtpCode $code,
    ): void {
        try {
            SmsIr::verifySend(
                mobile: (string) $phone,
                templateId: $this->templateId,
                parameters: $this->verificationParameters($code),
            );
        } catch (Throwable $exception) {
            throw $exception;
        }
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private function verificationParameters(
        OtpCode $code,
    ): array {
        return [
            [
                'name' => $this->parameterName,
                'value' => (string) $code,
            ],
        ];
    }
}
