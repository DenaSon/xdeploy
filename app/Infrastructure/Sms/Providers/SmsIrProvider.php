<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms\Providers;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use App\Infrastructure\Sms\Exceptions\SmsSendingException;
use Ipe\Sdk\Exceptions\SmsException;
use Ipe\Sdk\Facades\SmsIr;
use Throwable;

final readonly class SmsIrProvider implements SmsProviderInterface
{
    public function __construct(
        private int $templateId,
        private int $expiringSoonTemplateId,
        private string $parameterName = 'Code',
        private string $expiringSoonParameterName = 'Hours',
    ) {}

    public function sendVerificationCode(
        PhoneNumber $phone,
        OtpCode $code,
    ): void {
        $this->sendTemplate(
            phone: $phone,
            templateId: $this->templateId,
            parameters: $this->verificationParameters(
                $code,
            ),
        );
    }

    public function sendCloudServerExpirationWarning(
        PhoneNumber $phone,
    ): void {
        $this->sendTemplate(
            phone: $phone,
            templateId: $this->expiringSoonTemplateId,
            parameters: [
                [
                    'name' => $this->expiringSoonParameterName,
                    'value' => '24',
                ],
            ],
        );
    }

    /**
     * @param array<int, array{name: string, value: string}> $parameters
     */
    private function sendTemplate(
        PhoneNumber $phone,
        int $templateId,
        array $parameters,
    ): void {
        if ($templateId <= 0) {
            throw SmsSendingException::permanent(
                'SMS template is not configured.',
            );
        }

        try {
            $result = SmsIr::verifySend(
                mobile: (string) $phone,
                templateId: $templateId,
                parameters: $parameters,
            );
        } catch (SmsException $exception) {
            throw $this->mapProviderException(
                $exception,
            );
        } catch (Throwable $exception) {
            throw SmsSendingException::transient(
                message: 'SMS provider request failed.',
                previous: $exception,
            );
        }

        if (
            ! is_object($result)
            || (int) ($result->status ?? 0) !== 1
        ) {
            throw SmsSendingException::permanent(
                'SMS provider rejected the request.',
            );
        }
    }

    private function mapProviderException(
        SmsException $exception,
    ): SmsSendingException {
        $statusCode = (int) $exception->getCode();

        if (
            $statusCode === 429
            || $statusCode >= 500
            || $statusCode === 0
        ) {
            return SmsSendingException::transient(
                message: 'SMS provider is temporarily unavailable.',
                previous: $exception,
            );
        }

        return SmsSendingException::permanent(
            message: 'SMS provider rejected the request.',
            previous: $exception,
        );
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
