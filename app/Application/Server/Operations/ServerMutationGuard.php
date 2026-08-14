<?php

declare(strict_types=1);

namespace App\Application\Server\Operations;

use App\Application\Server\Operations\Exceptions\ServerMutationInProgressException;
use App\Models\ApplicationOperation;
use App\Models\PublicEndpointOperation;
use App\Models\Server;

final readonly class ServerMutationGuard
{
    public function ensureAvailable(Server $server): void
    {
        if ($this->isBusy($server)) {
            throw new ServerMutationInProgressException;
        }
    }

    public function isBusy(Server $server): bool
    {
        return ApplicationOperation::query()
            ->where('server_id', $server->getKey())
            ->active()
            ->exists()
            || PublicEndpointOperation::query()
                ->where('server_id', $server->getKey())
                ->active()
                ->exists();
    }
}
