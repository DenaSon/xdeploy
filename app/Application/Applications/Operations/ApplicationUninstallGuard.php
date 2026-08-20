<?php

declare(strict_types=1);

namespace App\Application\Applications\Operations;

use App\Application\Applications\Operations\Exceptions\ApplicationUninstallBlockedByPublicEndpointException;
use App\Domain\Application\Shared\Enums\ApplicationType;
use App\Models\PublicEndpoint;
use App\Models\Server;

final readonly class ApplicationUninstallGuard
{
    public function ensureAllowed(
        Server $server,
        ApplicationType $applicationType,
    ): void {
        $blockingEndpointExists = PublicEndpoint::query()
            ->where('server_id', $server->getKey())
            ->where(
                'application_type',
                $applicationType->value,
            )
            ->whereNull('disabled_at')
            ->exists();

        if ($blockingEndpointExists) {
            throw new ApplicationUninstallBlockedByPublicEndpointException(
                sprintf(
                    'Application [%s] cannot be uninstalled while its public endpoint is active or pending.',
                    $applicationType->value,
                ),
            );
        }
    }
}
