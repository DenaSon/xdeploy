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

        $expectedPanels = [
            ApplicationType::Marzban->value => 'applications.marzban.management-panel',
            ApplicationType::N8n->value => 'applications.n8n.management-panel',
            ApplicationType::AmneziaWg->value => 'applications.amnezia-wg.management-panel',
        ];

        $applicationTypes = array_map(
            static fn (ApplicationType $type): string => $type->value,
            ApplicationType::cases(),
        );

        $panelTypes = array_keys($expectedPanels);

        sort($applicationTypes);
        sort($panelTypes);

        self::assertSame(
            $applicationTypes,
            $panelTypes,
        );

        foreach ($expectedPanels as $type => $panel) {
            self::assertSame(
                $panel,
                $resolver->resolve(
                    ApplicationType::from($type),
                ),
            );
        }
    }
}
