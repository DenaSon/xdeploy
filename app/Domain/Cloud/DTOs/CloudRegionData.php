<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudRegionData
{
    public function __construct(
        public string $id,
        public ?string $displayName,
        public ?string $country,
        public ?string $city,
        public ?string $dataCenter,
        public bool $canCreateServers,
        public bool $isVisible,
        public bool $supportsVolumeBacked,
    ) {}
}
