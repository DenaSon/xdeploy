<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\CpuInfoData;
use App\Domain\Server\Services\ServerService;

final readonly class GetCpuInformationAction
{
    public function __construct(
        private ServerService $server,
    ) {}

    public function handle(): CpuInfoData
    {
        return $this->server->cpu();
    }
}
