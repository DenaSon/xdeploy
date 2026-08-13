<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domain\Application\AmneziaWg\AmneziaWgApplication;
use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use Tests\TestCase;

final class ApplicationServiceProviderTest extends TestCase
{
    public function test_application_registry_resolves_amneziawg(): void
    {
        $registry = $this->app->make(
            ApplicationRegistryInterface::class,
        );

        $this->assertInstanceOf(
            AmneziaWgApplication::class,
            $registry->find(
                ApplicationType::AmneziaWg,
            ),
        );
    }
}
