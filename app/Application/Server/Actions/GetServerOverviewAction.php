<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\ServerOverviewData;
use App\Domain\Server\Services\ServerService;

final readonly class GetServerOverviewAction
{
    public function __construct(
        private ServerService $server,
    ) {}

    public function handle(): ServerOverviewData
    {
        return $this->server->overview();
    }
}
