<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudIpVersion;

final readonly class CloudNetworkData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public CloudIpVersion $ipVersion,
        public ?string $cidr,
        public ?string $gateway,
        public bool $isActive,
        public bool $dhcpEnabled,
    ) {}
}
