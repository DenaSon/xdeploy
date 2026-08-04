<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudServerStatus;
use DateTimeImmutable;

final readonly class CloudServerData
{
    /**
     * @param  list<CloudServerAddressData>  $addresses
     * @param  list<string>  $networkIds
     * @param  list<string>  $securityGroupIds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public CloudServerStatus $status,
        public ?string $username,
        public ?string $sizeId,
        public ?string $imageId,
        public ?DateTimeImmutable $createdAt,
        public array $addresses = [],
        public array $networkIds = [],
        public array $securityGroupIds = [],
        public bool $volumeBacked = false,
        public bool $highAvailability = false,
    ) {}

    /**
     * @return list<string>
     */
    public function publicIpv4s(): array
    {
        $addresses = [];

        foreach ($this->addresses as $address) {
            if (! $address->isPublicIpv4()) {
                continue;
            }

            $addresses[] = $address->address;
        }

        return array_values(
            array_unique($addresses),
        );
    }

    public function firstPublicIpv4(): ?string
    {
        return $this->publicIpv4s()[0] ?? null;
    }

    public function hasPublicIpv4(): bool
    {
        return $this->publicIpv4s() !== [];
    }

    public function isReadyForSshCheck(): bool
    {
        return $this->status->isReady()
            && $this->hasPublicIpv4()
            && is_string($this->username)
            && trim($this->username) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region_id' => $this->regionId,
            'status' => $this->status->value,
            'username' => $this->username,
            'size_id' => $this->sizeId,
            'image_id' => $this->imageId,
            'created_at' => $this->createdAt?->format(
                DATE_ATOM,
            ),
            'addresses' => array_map(
                static fn (
                    CloudServerAddressData $address,
                ): array => $address->toArray(),
                $this->addresses,
            ),
            'network_ids' => $this->networkIds,
            'security_group_ids' => $this->securityGroupIds,
            'volume_backed' => $this->volumeBacked,
            'high_availability' => $this->highAvailability,
        ];
    }
}
