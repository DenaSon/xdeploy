<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Application\Authentication\Actions\RequestOtpAction;
use App\Domain\Authentication\DTOs\RequestOtpData;
use App\Domain\Authentication\Services\OtpService;
use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;
use App\Infrastructure\Persistence\Repositories\EloquentOtpRepository;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use App\Infrastructure\Sms\Exceptions\SmsSendingException;
use App\Infrastructure\Sms\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OtpBrowserConsoleDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_flow_does_not_return_plaintext_otp(): void
    {
        config()->set(
            'services.sms.otp_browser_console_debug',
            false,
        );

        $result = $this->action(
            smsFails: false,
        )->handle(
            new RequestOtpData(
                phone: PhoneNumber::from(
                    '09121111111',
                ),
            ),
        );

        self::assertNull($result);
    }

    public function test_normal_flow_still_fails_when_sms_delivery_fails(): void
    {
        config()->set(
            'services.sms.otp_browser_console_debug',
            false,
        );

        $this->expectException(
            SmsSendingException::class,
        );

        $this->action(
            smsFails: true,
        )->handle(
            new RequestOtpData(
                phone: PhoneNumber::from(
                    '09122222222',
                ),
            ),
        );
    }

    public function test_debug_flow_returns_valid_otp_when_sms_succeeds(): void
    {
        config()->set(
            'services.sms.otp_browser_console_debug',
            true,
        );

        $phone = PhoneNumber::from(
            '09123333333',
        );

        $code = $this->action(
            smsFails: false,
        )->handle(
            new RequestOtpData(
                phone: $phone,
            ),
        );

        self::assertInstanceOf(
            OtpCode::class,
            $code,
        );

        $this->otpService()->validate(
            phone: $phone,
            code: $code,
        );
    }

    public function test_debug_flow_keeps_valid_otp_when_sms_delivery_fails(): void
    {
        config()->set(
            'services.sms.otp_browser_console_debug',
            true,
        );

        $phone = PhoneNumber::from(
            '09124444444',
        );

        $code = $this->action(
            smsFails: true,
        )->handle(
            new RequestOtpData(
                phone: $phone,
            ),
        );

        self::assertInstanceOf(
            OtpCode::class,
            $code,
        );

        $this->otpService()->validate(
            phone: $phone,
            code: $code,
        );
    }

    public function test_verify_page_displays_debug_otp_from_ephemeral_browser_storage_safely(): void
    {
        $template = file_get_contents(
            resource_path(
                'views/livewire/auth/verify-otp-page.blade.php',
            ),
        );

        self::assertIsString($template);
        self::assertStringContainsString(
            'data-otp-browser-debug-panel',
            $template,
        );
        self::assertStringContainsString(
            'data-otp-browser-debug-code',
            $template,
        );
        self::assertStringContainsString(
            "window.sessionStorage.removeItem(storageKey);",
            $template,
        );
        self::assertStringContainsString(
            'code.textContent = otp;',
            $template,
        );
        self::assertStringContainsString(
            "panel.classList.remove('hidden');",
            $template,
        );
        self::assertStringNotContainsString(
            'innerHTML',
            $template,
        );
    }

    private function action(
        bool $smsFails,
    ): RequestOtpAction {
        $provider = new class($smsFails) implements SmsProviderInterface
        {
            public function __construct(
                private readonly bool $smsFails,
            ) {}

            public function sendVerificationCode(
                PhoneNumber $phone,
                OtpCode $code,
            ): void {
                if ($this->smsFails) {
                    throw SmsSendingException::transient(
                        'SMS provider unavailable for test.',
                    );
                }
            }

            public function sendCloudServerExpirationWarning(
                PhoneNumber $phone,
            ): void {}
        };

        return new RequestOtpAction(
            otpService: $this->otpService(),
            smsService: new SmsService(
                provider: $provider,
            ),
        );
    }

    private function otpService(): OtpService
    {
        return new OtpService(
            repository: new EloquentOtpRepository,
        );
    }
}
