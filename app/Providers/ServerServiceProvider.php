<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Server\Contracts\SystemPackageManager;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\Linux\Distributions\UbuntuDistribution;
use App\Infrastructure\Linux\Packages\AptPackageManager;
use App\Infrastructure\SSH\Authentication\AuthenticationStrategyFactory;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Contracts\SSHHostResolverInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
use App\Infrastructure\SSH\Services\SystemSSHHostResolver;
use Illuminate\Support\ServiceProvider;

final class ServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         * SSHConnection is stateful:
         * it stores the current SSH transport and target server.
         *
         * It must be shared within one request/job lifecycle,
         * but never across different lifecycles.
         */
        $this->app->scoped(
            SSHConnectionInterface::class,
            SSHConnection::class,
        );
        $this->app->singleton(
            SSHHostResolverInterface::class,
            SystemSSHHostResolver::class,
        );

        /*
         * AptPackageManager captures SSHConnectionInterface
         * in its constructor, so it must use the same lifecycle.
         */
        $this->app->scoped(
            SystemPackageManager::class,
            AptPackageManager::class,
        );

        /*
         * UbuntuDistribution only contains command definitions
         * and has no server/request state.
         */
        $this->app->singleton(
            LinuxDistribution::class,
            UbuntuDistribution::class,
        );

        /*
         * AuthenticationStrategyFactory does not represent
         * a server connection/session and may remain singleton.
         */
        $this->app->singleton(
            AuthenticationStrategyFactory::class,
        );
    }
}
