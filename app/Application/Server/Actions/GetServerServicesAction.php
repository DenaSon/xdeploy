<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\ServiceStatusData;
use App\Domain\Server\Services\ServerService;
use App\Models\Server;

final readonly class GetServerServicesAction
{
    public function __construct(
        private ConnectServerAction $connectServer,
        private ServerService $serverService,
    ) {}

    /**
     * @return list<ServiceStatusData>
     */
    public function handle(Server $server): array
    {
        $this->connectServer->handle($server);

        return $this->serverService->services();
    }
}
