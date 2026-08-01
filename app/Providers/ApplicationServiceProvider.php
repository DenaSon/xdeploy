<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Marzban\Admin\MarzbanAdminGateway;
use App\Domain\Application\Marzban\MarzbanApplication;
use App\Domain\Application\Registry\ApplicationRegistry;
use App\Infrastructure\Application\Marzban\SshMarzbanAdminGateway;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ApplicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MarzbanAdminGateway::class,
            SshMarzbanAdminGateway::class,
        );

        $this->app->singleton(
            ApplicationRegistryInterface::class,
            fn (Application $app): ApplicationRegistry => new ApplicationRegistry(
                $this->applications($app),
            ),
        );
    }

    /**
     * @return list<ApplicationInterface>
     *
     * @throws BindingResolutionException
     */
    private function applications(Application $app): array
    {
        return [
            $app->make(MarzbanApplication::class),
        ];
    }
}
