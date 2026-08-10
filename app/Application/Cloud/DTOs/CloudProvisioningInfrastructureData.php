<?php

declare(strict_types=1);

namespace App\Application\Cloud\DTOs;

final readonly class CloudProvisioningInfrastructureData
{
    /**
     * @param  non-empty-list<string>  $securityGroupIds
     */
    public function __construct(
        public string $networkId,
        public array $securityGroupIds,
    ) {}
}
