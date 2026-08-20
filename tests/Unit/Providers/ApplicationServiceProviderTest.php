<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Application\PublicEndpoint\Drivers\MarzbanPublicEndpointDriver;
use App\Application\PublicEndpoint\Drivers\N8nPublicEndpointDriver;
use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
use App\Domain\Application\AmneziaWg\AmneziaWgApplication;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Marzban\MarzbanApplication;
use App\Domain\Application\N8n\N8nApplication;
use App\Domain\Application\Shared\Enums\ApplicationType;
use Tests\TestCase;

final class ApplicationServiceProviderTest extends TestCase
{
    public function test_application_registry_contains_all_supported_applications(): void
    {
        $registry = $this->app->make(
            ApplicationRegistryInterface::class,
        );

        $expectedApplications = [
            ApplicationType::Marzban->value => MarzbanApplication::class,
            ApplicationType::N8n->value => N8nApplication::class,
            ApplicationType::AmneziaWg->value => AmneziaWgApplication::class,
        ];

        $applicationTypes = array_map(
            static fn (ApplicationType $type): string => $type->value,
            ApplicationType::cases(),
        );

        $registeredTypes = array_keys($expectedApplications);

        sort($applicationTypes);
        sort($registeredTypes);

        $this->assertSame(
            $applicationTypes,
            $registeredTypes,
        );

        $this->assertCount(
            count(ApplicationType::cases()),
            $registry->all(),
        );

        foreach ($expectedApplications as $type => $applicationClass) {
            $this->assertInstanceOf(
                $applicationClass,
                $registry->find(
                    ApplicationType::from($type),
                ),
            );
        }
    }

    public function test_public_endpoint_registry_contains_supported_drivers(): void
    {
        $registry = $this->app->make(
            PublicEndpointDriverRegistry::class,
        );

        $this->assertInstanceOf(
            MarzbanPublicEndpointDriver::class,
            $registry->find(ApplicationType::Marzban),
        );

        $this->assertInstanceOf(
            N8nPublicEndpointDriver::class,
            $registry->find(ApplicationType::N8n),
        );

        $this->assertCount(2, $registry->all());
    }
}
