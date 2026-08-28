<?php

declare(strict_types=1);

namespace App\Application\Cloud\DTOs;

final readonly class CloudProvisioningPollingSettings
{
    public function __construct(
        public int $maxAttempts,
        public int $pollDelaySeconds,
    ) {}
}
