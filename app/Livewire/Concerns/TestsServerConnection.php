<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Application\Server\Actions\TestServerConnectionAction;
use App\Application\Server\Data\TestServerConnectionData;
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
            if (
                ! $action->execute(
                    TestServerConnectionData::from(
                        $data,
                    ),
                )
            ) {
                $this->error(
                    'اتصال ناموفق',
                    'امکان برقراری ارتباط با سرور وجود ندارد. اطلاعات اتصال را بررسی کنید.',
                );

                return;
            }

            $this->success(
                'اتصال موفق',
                'ارتباط SSH با سرور با موفقیت برقرار شد.',
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
                'خطایی هنگام برقراری ارتباط با سرور رخ داد.',
            );
        }
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
