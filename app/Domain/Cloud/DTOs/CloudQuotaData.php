<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudQuotaData
{
    public function __construct(
        public string $regionId,
        public ?int $instancesLimit,
        public ?int $instancesUsed,
        public ?int $vCpuLimit,
        public ?int $vCpuUsed,
        public ?int $memoryMiBLimit,
        public ?int $memoryMiBUsed,
        public ?int $sshKeysLimit,
        public ?int $sshKeysUsed,
    ) {}
}
