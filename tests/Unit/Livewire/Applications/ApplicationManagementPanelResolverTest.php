<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Applications;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Livewire\Applications\Resolvers\ApplicationManagementPanelResolver;
use PHPUnit\Framework\TestCase;

final class ApplicationManagementPanelResolverTest extends TestCase
{
    public function test_it_resolves_management_panels_for_supported_applications(): void
    {
        $resolver = new ApplicationManagementPanelResolver;

        self::assertSame(
            'applications.marzban.management-panel',
            $resolver->resolve(
                ApplicationType::Marzban,
            ),
        );

        self::assertSame(
            'applications.n8n.management-panel',
            $resolver->resolve(
                ApplicationType::N8n,
            ),
        );
    }
}
