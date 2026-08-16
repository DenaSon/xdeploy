<?php

declare(strict_types=1);

namespace App\Application\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudProviderType;

final readonly class CloudServerTargetData
{
    public function __construct(
        public CloudProviderType $provider,
        public string $region,
        public string $serverId,
    ) {}
}
