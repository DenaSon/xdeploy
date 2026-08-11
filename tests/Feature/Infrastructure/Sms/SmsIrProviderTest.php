<?php

declare(strict_types=1);

namespace Tests\Feature\Infrastructure\Sms;

use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Sms\Exceptions\SmsSendingException;
use App\Infrastructure\Sms\Providers\SmsIrProvider;
use Ipe\Sdk\Facades\SmsIr;
use Tests\TestCase;

final class SmsIrProviderTest extends TestCase
{
    public function test_http_400_style_result_is_treated_as_permanent_failure(): void
    {
        SmsIr::shouldReceive('verifySend')
            ->once()
            ->andReturn((object) [
                'status' => 0,
                'message' => 'Invalid template.',
                'data' => null,
            ]);

        $provider = $this->provider();

        try {
            $provider->sendCloudServerExpirationWarning(
                PhoneNumber::from('09123456789'),
            );

            $this->fail(
                'Expected SmsSendingException was not thrown.',
            );
        } catch (SmsSendingException $exception) {
            $this->assertFalse(
                $exception->isRetryable(),
            );

            $this->assertSame(
                'SMS provider rejected the request.',
                $exception->getMessage(),
            );
        }
    }

    public function test_expiry_warning_uses_dedicated_template_and_hours_parameter(): void
    {
        SmsIr::shouldReceive('verifySend')
            ->once()
            ->withArgs(
                static fn (
                    string $mobile,
                    int $templateId,
                    array $parameters,
                ): bool => $mobile === '09123456789'
                    && $templateId === 200
                    && $parameters === [
                        [
                            'name' => 'Hours',
                            'value' => '24',
                        ],
                    ],
            )
            ->andReturn((object) [
                'status' => 1,
                'message' => 'Successful operation.',
                'data' => [
                    'messageId' => 123,
                ],
            ]);

        $this->provider()
            ->sendCloudServerExpirationWarning(
                PhoneNumber::from('09123456789'),
            );

        $this->addToAssertionCount(1);
    }

    private function provider(): SmsIrProvider
    {
        return new SmsIrProvider(
            templateId: 100,
            expiringSoonTemplateId: 200,
            parameterName: 'Code',
            expiringSoonParameterName: 'Hours',
        );
    }
}
