<?php

declare(strict_types=1);

namespace Tests\Unit\Livewire\Applications\Resolvers;

use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Livewire\Applications\Resolvers\ApplicationManagementPanelResolver;
use PHPUnit\Framework\TestCase;

final class ApplicationManagementPanelResolverTest extends TestCase
{
    public function test_it_resolves_the_amneziawg_management_panel(): void
    {
        $resolver = new ApplicationManagementPanelResolver;

        self::assertSame(
            'applications.amnezia-wg.management-panel',
            $resolver->resolve(
                ApplicationType::AmneziaWg,
            ),
        );
    }
}
