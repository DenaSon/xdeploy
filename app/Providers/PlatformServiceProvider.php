<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\Contracts\PlatformRegistryInterface;
use App\Domain\Platform\Docker\DockerPlatform;
use App\Domain\Platform\DockerCompose\DockerComposePlatform;
use App\Domain\Platform\Registry\PlatformRegistry;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Register platform services.
     */
    public function register(): void
    {
        $this->app->singleton(
            PlatformRegistryInterface::class,
            fn (Application $app) => new PlatformRegistry(
                $this->platforms($app),
            ),
        );
    }

    /**
     * @return list<PlatformInterface>
     *
     * @throws BindingResolutionException
     */
    private function platforms(Application $app): array
    {
        return [
            $app->make(DockerPlatform::class),
            $app->make(DockerComposePlatform::class),
        ];
    }
}
