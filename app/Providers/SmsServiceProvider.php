<?php

namespace App\Providers;

use App\Domain\Authentication\Contracts\OtpRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\EloquentOtpRepository;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use App\Infrastructure\Sms\Providers\SmsIrProvider;
use Illuminate\Support\ServiceProvider;

class SmsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
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

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
