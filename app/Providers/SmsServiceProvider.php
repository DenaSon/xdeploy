<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Authentication\Contracts\OtpRepositoryInterface;
use App\Infrastructure\Persistence\Repositories\EloquentOtpRepository;
use App\Infrastructure\Sms\Contracts\SmsProviderInterface;
use App\Infrastructure\Sms\Providers\FakeSmsProvider;
use App\Infrastructure\Sms\Providers\SmsIrProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class SmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerRepositories();
        $this->registerSmsProvider();
    }

    private function registerRepositories(): void
    {
        $this->app->bind(
            OtpRepositoryInterface::class,
            EloquentOtpRepository::class,
        );
    }

    private function registerSmsProvider(): void
    {
        $this->app->singleton(
            SmsProviderInterface::class,
            function (Application $app): SmsProviderInterface {
                $driver = $app->make('config')->string(
                    'services.sms.driver',
                    'fake',
                );

                return match ($driver) {
                    'fake' => new FakeSmsProvider,

                    'smsir' => new SmsIrProvider(
                        templateId: $app->make('config')->integer(
                            'services.smsir.template_id',
                        ),
                        expiringSoonTemplateId: $app->make('config')->integer(
                            'services.smsir.expiring_soon_template_id',
                        ),
                        parameterName: $app->make('config')->string(
                            'services.smsir.parameter_name',
                            'Code',
                        ),
                        expiringSoonParameterName: $app->make('config')->string(
                            'services.smsir.expiring_soon_parameter_name',
                            'Hours',
                        ),
                    ),

                    default => throw new InvalidArgumentException(
                        "Unsupported SMS driver [{$driver}].",
                    ),
                };
            },
        );
    }
}
