<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\ResourceUsageData;
use App\Domain\Server\Services\ServerService;

final readonly class GetResourceUsageAction
{
    public function __construct(
        private ServerService $server,
    ) {}

    public function handle(): ResourceUsageData
    {
        return $this->server->resourceUsage();
    }
}
