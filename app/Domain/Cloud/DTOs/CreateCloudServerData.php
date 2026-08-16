<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CreateCloudServerData
{
    /**
     * @param  list<string>  $securityGroupIds
     */
    public function __construct(
        public string $name,
        public string $regionId,
        public string $sizeId,
        public string $imageId,
        public int $diskGiB,
        public ?string $networkId = null,
        public array $securityGroupIds = [],
        public ?string $sshKeyName = null,
        public string $initializationScript = '',
        public bool $highAvailability = false,
    ) {}

    public function usesSshKey(): bool
    {
        return is_string($this->sshKeyName)
            && trim($this->sshKeyName) !== '';
    }

    public function hasProvisioningInfrastructure(): bool
    {
        return is_string($this->networkId)
            && trim($this->networkId) !== ''
            && $this->securityGroupIds !== [];
    }

    public function hasAnyProvisioningInfrastructure(): bool
    {
        return (
            is_string($this->networkId)
            && trim($this->networkId) !== ''
        ) || $this->securityGroupIds !== [];
    }
}
