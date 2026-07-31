<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Marzban\MarzbanApplication;
use App\Domain\Application\Registry\ApplicationRegistry;
use App\Domain\Server\Contracts\SystemPackageManager;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\Linux\Distributions\UbuntuDistribution;
use App\Infrastructure\Linux\Packages\AptPackageManager;
use App\Infrastructure\SSH\Authentication\AuthenticationStrategyFactory;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            SSHConnectionInterface::class,
            SSHConnection::class,
        );

        $this->app->singleton(
            LinuxDistribution::class,
            UbuntuDistribution::class,
        );

        $this->app->singleton(
            SystemPackageManager::class,
            AptPackageManager::class,
        );

        $this->app->singleton(
            ApplicationRegistryInterface::class,
            fn (Application $app) => new ApplicationRegistry(
                $this->applications($app),
            ),
        );

        $this->app->singleton(
            AuthenticationStrategyFactory::class,
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
