<?php

namespace App\Providers;

use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\Modules\Docker\DockerModule;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\Linux\Distributions\UbuntuDistribution;
use App\Infrastructure\Module\ModuleRegistry;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
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
            ModuleRegistryInterface::class,
            function (Application $app) {
                return new ModuleRegistry([
                    $app->make(DockerModule::class),
                ]);
            }
        );

    }
}
