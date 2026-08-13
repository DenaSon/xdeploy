<?php

declare(strict_types=1);

namespace App\Livewire\Applications\Resolvers;

use App\Domain\Application\Shared\Enums\ApplicationType;

final class ApplicationManagementPanelResolver
{
    public function resolve(ApplicationType $type): string
    {
        return match ($type) {
            ApplicationType::Marzban => 'applications.marzban.management-panel',
            ApplicationType::N8n => 'applications.n8n.management-panel',
            ApplicationType::AmneziaWg => 'applications.amnezia-wg.management-panel',
        };
    }
}
