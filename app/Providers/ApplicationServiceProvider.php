<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\PublicEndpoint\Drivers\MarzbanPublicEndpointDriver;
use App\Application\PublicEndpoint\Drivers\N8nPublicEndpointDriver;
use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Domain\Application\Contracts\ApplicationInterface;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Marzban\Admin\MarzbanAdminGateway;
use App\Domain\Application\Marzban\Admin\MarzbanAdminReader;
use App\Domain\Application\Marzban\Https\MarzbanHttpsDisabler;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;
use App\Domain\Application\Marzban\MarzbanApplication;
use App\Domain\Application\N8n\N8nApplication;
use App\Domain\Application\N8n\PublicEndpoint\N8nPublicEndpointGateway;
use App\Domain\Application\Registry\ApplicationRegistry;
use App\Infrastructure\Application\Marzban\Https\SshMarzbanHttpsDisabler;
use App\Infrastructure\Application\Marzban\SshMarzbanAdminGateway;
use App\Infrastructure\Application\Marzban\SshMarzbanHttpsGateway;
use App\Infrastructure\Application\N8n\PublicEndpoint\SshN8nPublicEndpointGateway;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ApplicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MarzbanAdminGateway::class,
            SshMarzbanAdminGateway::class,
        );

        $this->app->bind(
            MarzbanAdminReader::class,
            SshMarzbanAdminGateway::class,
        );

        $this->app->bind(
            MarzbanHttpsGateway::class,
            SshMarzbanHttpsGateway::class,
        );

        $this->app->bind(
            MarzbanHttpsDisabler::class,
            SshMarzbanHttpsDisabler::class,
        );

        $this->app->bind(
            N8nPublicEndpointGateway::class,
            SshN8nPublicEndpointGateway::class,
        );

        /*
         * Applications contain lifecycle-scoped SSH dependencies.
         *
         * The registry therefore must not survive beyond
         * the request/job lifecycle that created them.
         */
        $this->app->scoped(
            ApplicationRegistryInterface::class,
            fn (Application $app): ApplicationRegistry => new ApplicationRegistry(
                $this->applications($app),
            ),
        );

        $this->app->scoped(
            PublicEndpointDriverRegistry::class,
            fn (Application $app): PublicEndpointDriverRegistry => new PublicEndpointDriverRegistry([
                $app->make(MarzbanPublicEndpointDriver::class),
                $app->make(N8nPublicEndpointDriver::class),
            ]),
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
            $app->make(N8nApplication::class),
        ];
    }
}
