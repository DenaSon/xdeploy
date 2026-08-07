<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\ServiceStatusData;
use App\Domain\Server\Services\ServerService;

final readonly class GetServerServicesAction
{
    public function __construct(
        private ServerService $serverService,
    ) {}

    /**
     * @return list<ServiceStatusData>
     */
    public function execute(): array
    {
        return $this->serverService->services();
    }
}
