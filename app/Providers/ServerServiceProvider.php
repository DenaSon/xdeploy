<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Server\Contracts\SystemPackageManager;
use App\Domain\Server\Services\SupportedOperatingSystemPolicy;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\Linux\Distributions\DebianFamilyDistribution;
use App\Infrastructure\Linux\Packages\AptPackageManager;
use App\Infrastructure\SSH\Authentication\AuthenticationStrategyFactory;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Contracts\SSHHostResolverInterface;
use App\Infrastructure\SSH\Contracts\SSHPortReadinessProbeInterface;
use App\Infrastructure\SSH\Services\SSHConnection;
use App\Infrastructure\SSH\Services\SSHPortReadinessProbe;
use App\Infrastructure\SSH\Services\SystemSSHHostResolver;
use Illuminate\Support\ServiceProvider;

final class ServerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(
            SSHConnectionInterface::class,
            SSHConnection::class,
        );

        $this->app->singleton(
            SSHPortReadinessProbeInterface::class,
            SSHPortReadinessProbe::class,
        );

        $this->app->singleton(
            SSHHostResolverInterface::class,
            SystemSSHHostResolver::class,
        );

        $this->app->scoped(
            SystemPackageManager::class,
            AptPackageManager::class,
        );

        $this->app->singleton(
            LinuxDistribution::class,
            DebianFamilyDistribution::class,
        );

        $this->app->singleton(
            AuthenticationStrategyFactory::class,
        );

        $this->app->singleton(
            SupportedOperatingSystemPolicy::class,
            static fn (): SupportedOperatingSystemPolicy => new SupportedOperatingSystemPolicy(
                matrix: (array) config(
                    'supported_os.matrix',
                    [],
                ),
            ),
        );
    }
}
