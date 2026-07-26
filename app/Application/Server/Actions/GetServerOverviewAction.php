<?php

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\ServerOverviewData;
use App\Domain\Server\Services\ServerService;

readonly class GetServerOverviewAction
{
    public function __construct(
        private ServerService $server,
    ) {}

    /**
     * Get the current server overview.
     */
    public function handle(): ServerOverviewData
    {
        return $this->server->overview();
    }
}
