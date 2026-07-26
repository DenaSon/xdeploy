<?php

namespace App\Providers;

use App\Domain\Authentication\Contracts\OtpRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\EloquentOtpRepository;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use App\Infrastructure\Sms\Providers\SmsIrProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            OtpRepositoryInterface::class,
            EloquentOtpRepository::class,
        );

        $this->app->singleton(
            SmsProviderInterface::class,
            fn () => new SmsIrProvider(
                templateId: config('services.smsir.template_id'),
                parameterName: config('services.smsir.parameter_name', 'Code'),
            ),
        );
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}
