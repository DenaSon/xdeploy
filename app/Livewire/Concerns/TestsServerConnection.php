<?php

namespace App\Livewire\Concerns;

use App\Application\Server\Actions\TestServerConnectionAction;
use App\Application\Server\Data\TestServerConnectionData;
use Mary\Traits\Toast;
use Throwable;

trait TestsServerConnection
{
    use Toast;

    public function testConnection(TestServerConnectionAction $action): void
    {
        $data = TestServerConnectionData::from(
            $this->validate()
        );

        try {

            if (! $action->execute($data)) {

                $this->error(
                    'اتصال ناموفق',
                    'امکان برقراری ارتباط با سرور وجود ندارد. اطلاعات اتصال را بررسی کنید.'
                );

                return;
            }

            $this->success(
                'اتصال موفق',
                'ارتباط SSH با سرور با موفقیت برقرار شد.'
            );

        } catch (Throwable $exception) {

            report($exception);

            $this->error(
                'خطا',
                'خطایی هنگام برقراری ارتباط با سرور رخ داد.'
            );

        }
    }
}
