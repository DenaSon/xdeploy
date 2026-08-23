<?php

namespace App\Providers;

use App\Domain\Application\Shared\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\Contracts\ApplicationRuntimeGatewayInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\Shared\Registry\ApplicationDefinition;
use App\Domain\Application\Shared\Registry\ApplicationRegistry;
use App\Domain\Application\Shared\Services\ApplicationPathResolver;
use App\Domain\Application\Shared\Services\ApplicationReadinessChecker;
use App\Domain\Application\Shared\Services\ApplicationRequiresService;
use App\Domain\Application\Shared\Services\ApplicationRuntime;
use App\Domain\Application\Shared\Services\SystemDependencyService;
use App\Domain\Application\Shared\Services\SystemDependencyStateInspector;
use App\Domain\Application\Shared\Services\SystemdApplicationRuntime;
use App\Domain\Application\Shared\Support\Files\ApplicationAssetReader;
use App\Domain\Application\Shared\Support\Files\ApplicationAssetReaderInterface;
use App\Infrastructure\Application\Shared\SshApplicationRuntimeGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ApplicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApplicationRuntimeGatewayInterface::class, SshApplicationRuntimeGateway::class);

        $this->app->singleton(ApplicationAssetReaderInterface::class, fn (): ApplicationAssetReaderInterface => new ApplicationAssetReader);
        $this->app->singleton(ApplicationPathResolver::class);
        $this->app->singleton(ApplicationReadinessChecker::class);
        $this->app->singleton(ApplicationRequiresService::class);
        $this->app->singleton(SystemDependencyService::class);
        $this->app->singleton(SystemDependencyStateInspector::class);
        $this->app->singleton(SystemdApplicationRuntime::class);

        $this->app->singleton(ApplicationRuntime::class, fn (Application $app): ApplicationRuntime => new ApplicationRuntime(
            $app->make(ApplicationPathResolver::class),
            $app->make(SystemDependencyStateInspector::class),
            $app->make(SystemdApplicationRuntime::class),
        ));

        $this->app->singleton(ApplicationRegistryInterface::class, function (Application $app): ApplicationRegistryInterface {
            return new ApplicationRegistry([
                ApplicationType::Marzban => new ApplicationDefinition(
                    $app->make(\App\Domain\Application\Marzban\Services\MarzbanApplication::class),
                    $app->make(\App\Application\Applications\Marzban\Services\MarzbanManager::class),
                ),
                ApplicationType::N8n => new ApplicationDefinition(
                    $app->make(\App\Domain\Application\N8n\Services\N8nApplication::class),
                    $app->make(\App\Application\Applications\N8n\Services\N8nManager::class),
                ),
                ApplicationType::WordPress => new ApplicationDefinition(
                    $app->make(\App\Domain\Application\WordPress\Services\WordPressApplication::class),
                    $app->make(\App\Application\Applications\WordPress\Services\WordPressManager::class),
                ),
            ]);
        });
    }
}
