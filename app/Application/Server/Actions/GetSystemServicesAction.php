<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\SystemServiceData;
use App\Domain\Server\Services\ServerService;

final readonly class GetSystemServicesAction
{
    public function __construct(
        private ServerService $server,
    ) {}

    /**
     * @return list<SystemServiceData>
     */
    public function handle(): array
    {
        return $this->server->services();
    }
}
