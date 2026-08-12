<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Application\PublicEndpoint\Drivers\MarzbanPublicEndpointDriver;
use App\Application\PublicEndpoint\Drivers\N8nPublicEndpointDriver;
use App\Application\PublicEndpoint\PublicEndpointDriverRegistry;
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

        $this->assertInstanceOf(
            MarzbanApplication::class,
            $registry->find(ApplicationType::Marzban),
        );

        $this->assertInstanceOf(
            N8nApplication::class,
            $registry->find(ApplicationType::N8n),
        );
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
