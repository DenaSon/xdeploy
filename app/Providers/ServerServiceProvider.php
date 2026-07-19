<?php

namespace App\Providers;

use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\Linux\Distributions\UbuntuDistribution;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
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
    }
}
