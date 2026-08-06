<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudPortData
{
    /**
     * @param list<string> $ips
     * @param list<string> $securityGroupIds
     */
    public function __construct(
        public string $id,
        public string $serverId,
        public array $ips,
        public string $macAddress,
        public string $networkId,
        public bool $portSecurityEnabled,
        public array $securityGroupIds,
        public string $status,
    ) {}

    public function containsIp(string $ip): bool
    {
        return in_array(
            $ip,
            $this->ips,
            true,
        );
    }
}
