<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Application\Server\Actions\TestServerConnectionAction;
use App\Application\Server\Data\TestServerConnectionData;
use App\Application\Server\Data\TestServerConnectionResult;
use App\Application\Server\Enums\ServerConnectionTestStatus;
use App\Infrastructure\SSH\Exceptions\SSHConnectionTargetNotAllowedException;
use Mary\Traits\Toast;
use Throwable;

trait TestsServerConnection
{
    use Toast;

    public function testConnection(
        TestServerConnectionAction $action,
    ): void {
        $data = $this->validate();

        $data['credential'] =
            $this->credentialForConnectionTest();

        try {
            $result = $action->execute(
                TestServerConnectionData::from(
                    $data,
                ),
            );

            $this->showConnectionTestResult(
                $result,
            );
        } catch (SSHConnectionTargetNotAllowedException) {
            /*
             * Expected security-policy rejection.
             *
             * Do not report this as an application error.
             */
            $this->error(
                'آدرس سرور مجاز نیست',
                'برای اتصال، IP عمومی یا دامنه عمومی معتبر سرور را وارد کنید.',
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->error(
                'خطا',
                'خطایی هنگام بررسی آمادگی سرور رخ داد.',
            );
        }
    }

    private function showConnectionTestResult(
        TestServerConnectionResult $result,
    ): void {
        match ($result->status) {
            ServerConnectionTestStatus::Ready =>
                $this->showReadyConnection(
                    $result,
                ),

            ServerConnectionTestStatus::ConnectionFailed =>
                $this->error(
                    'اتصال ناموفق',
                    'امکان برقراری ارتباط SSH با سرور وجود ندارد. اطلاعات اتصال، شبکه و پورت SSH را بررسی کنید.',
                ),

            ServerConnectionTestStatus::PasswordChangeRequired =>
                $this->error(
                    'تغییر رمز عبور الزامی است',
                    'اتصال SSH برقرار شد، اما سیستم‌عامل پیش از اجرای دستورات درخواست تغییر رمز عبور دارد. یک‌بار مستقیماً از طریق SSH وارد سرور شوید، رمز را تغییر دهید و سپس دوباره اتصال را بررسی کنید.',
                ),

            ServerConnectionTestStatus::CommandUnavailable =>
                $this->error(
                    'امکان اجرای دستورات وجود ندارد',
                    'اتصال SSH برقرار شد، اما xDeploy نمی‌تواند دستورات موردنیاز را روی این سرور اجرا کند.',
                ),

            ServerConnectionTestStatus::UnsupportedOperatingSystem =>
                $this->showUnsupportedOperatingSystem(
                    $result,
                ),
        };
    }

    private function showReadyConnection(
        TestServerConnectionResult $result,
    ): void {
        $operatingSystem = $result->operatingSystem;

        $description = $operatingSystem === null
            ? 'سرور برای استفاده در xDeploy آماده است.'
            : sprintf(
                'اتصال برقرار شد و سیستم‌عامل %s پشتیبانی می‌شود.',
                $operatingSystem->displayName(),
            );

        $this->success(
            'سرور آماده است',
            $description,
        );
    }

    private function showUnsupportedOperatingSystem(
        TestServerConnectionResult $result,
    ): void {
        $operatingSystem = $result->operatingSystem;

        $detected = $operatingSystem?->displayName()
            ?? 'ناشناخته';

        $this->error(
            'سیستم‌عامل پشتیبانی نمی‌شود',
            sprintf(
                'سیستم‌عامل شناسایی‌شده %s است. نسخه فعلی xDeploy فقط از Ubuntu و Debian پشتیبانی می‌کند.',
                $detected,
            ),
        );
    }

    /**
     * Create uses the credential entered by the user.
     * Edit may override this without exposing the stored
     * credential through Livewire state.
     */
    protected function credentialForConnectionTest(): string
    {
        return $this->credential;
    }
}
