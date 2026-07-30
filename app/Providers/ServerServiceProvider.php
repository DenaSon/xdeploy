<?php

namespace App\Providers;

use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Registry\ApplicationRegistry;
use App\Domain\Platform\Docker\DockerPlatform;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\Linux\Distributions\UbuntuDistribution;
use App\Infrastructure\SSH\Authentication\AuthenticationStrategyFactory;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ServerServiceProvider extends ServiceProvider
{
    /**
     * Register server services.
     */
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
            ApplicationRegistryInterface::class,
            fn (Application $app) => new ApplicationRegistry(
                $this->modules($app),
            ),
        );

        $this->app->singleton(
            AuthenticationStrategyFactory::class
        );
    }

    /**
     * @return list<ApplicationInterface>
     *
     * @throws BindingResolutionException
     */
    private function modules(Application $app): array
    {
        return [
            $app->make(DockerPlatform::class),


        ];
    }
}
