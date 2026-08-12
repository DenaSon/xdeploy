<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Platform\Caddy\CaddyPlatform;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Platform\Contracts\PlatformInterface;
use App\Domain\Platform\Contracts\PlatformRegistryInterface;
use App\Domain\Platform\Docker\DockerPlatform;
use App\Domain\Platform\DockerCompose\DockerComposePlatform;
use App\Domain\Platform\Registry\PlatformRegistry;
use App\Infrastructure\Installers\Contracts\InstallerSourceInterface;
use App\Infrastructure\Installers\Sources\HttpInstallerSource;
use App\Infrastructure\Installers\Sources\LocalInstallerSource;
use App\Infrastructure\Platform\Caddy\SshCaddySiteManager;
use App\Infrastructure\Platform\Caddy\SshCaddySiteReader;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            InstallerSourceInterface::class,
            function (): InstallerSourceInterface {
                $source = strtolower(
                    trim(
                        (string) config(
                            'xdeploy.installers.source',
                            'local',
                        ),
                    ),
                );

                return match ($source) {
                    'local' => new LocalInstallerSource(
                        rootPath: (string) config(
                            'xdeploy.installers.local_root',
                        ),
                    ),

                    'http' => new HttpInstallerSource(
                        baseUrl: (string) config(
                            'xdeploy.installers.base_url',
                        ),
                    ),

                    default => throw new InvalidArgumentException(
                        sprintf(
                            'Unsupported xDeploy installer source [%s].',
                            $source,
                        ),
                    ),
                };
            },
        );

        $this->app->bind(
            CaddySiteManagerInterface::class,
            SshCaddySiteManager::class,
        );

        $this->app->bind(
            CaddySiteReaderInterface::class,
            SshCaddySiteReader::class,
        );

        /*
         * Registered platforms capture SSHConnectionInterface.
         *
         * The registry therefore belongs to the current
         * request/job lifecycle.
         */
        $this->app->scoped(
            PlatformRegistryInterface::class,
            fn (Application $app): PlatformRegistry => new PlatformRegistry(
                $this->platforms($app),
            ),
        );
    }

    /**
     * @return list<PlatformInterface>
     *
     * @throws BindingResolutionException
     */
    private function platforms(
        Application $app,
    ): array {
        return [
            $app->make(
                DockerPlatform::class,
            ),

            $app->make(
                DockerComposePlatform::class,
            ),

            $app->make(
                CaddyPlatform::class,
            ),
        ];
    }
}
