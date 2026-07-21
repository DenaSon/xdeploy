<?php

namespace App\Providers;

use App\Domain\Module\Contracts\ModuleInterface;
use App\Domain\Module\Contracts\ModuleRegistryInterface;
use App\Domain\Module\Modules\Composer\ComposerModule;
use App\Domain\Module\Modules\Docker\DockerModule;
use App\Domain\Module\Modules\DockerCompose\DockerComposeModule;
use App\Domain\Module\Modules\Git\GitModule;
use App\Domain\Module\Modules\Marzban\MarzbanModule;
use App\Domain\Module\Modules\Nginx\NginxModule;
use App\Domain\Module\Modules\Php\PhpModule;
use App\Domain\Module\Modules\Redis\RedisModule;
use App\Domain\Module\Registry\ModuleRegistry;
use App\Infrastructure\Linux\Contracts\LinuxDistribution;
use App\Infrastructure\Linux\Distributions\UbuntuDistribution;
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
            ModuleRegistryInterface::class,
            fn (Application $app) => new ModuleRegistry(
                $this->modules($app),
            ),
        );
    }

    /**
     * @return list<ModuleInterface>
     * @throws BindingResolutionException
     */
    private function modules(Application $app): array
    {
        return [
            $app->make(DockerModule::class),
            $app->make(DockerComposeModule::class),
            $app->make(NginxModule::class),
            $app->make(PhpModule::class),
            $app->make(ComposerModule::class),
            $app->make(GitModule::class),
            $app->make(RedisModule::class),
            $app->make(MarzbanModule::class),

        ];
    }
}
