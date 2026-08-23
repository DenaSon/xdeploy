<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domain\Application\Contracts\ApplicationRegistryInterface;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Domain\Application\WordPress\WordPressApplication;
use Tests\TestCase;

final class ApplicationServiceProviderTest extends TestCase
{
    public function test_application_registry_resolves_wordpress(): void
    {
        $registry = $this->app->make(
            ApplicationRegistryInterface::class,
        );

        $this->assertInstanceOf(
            WordPressApplication::class,
            $registry->find(
                ApplicationType::WordPress,
            ),
        );
    }
}
