<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudServerPowerState;
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

        /*
         * Fields added for resize discovery, polling, and verification.
         *
         * They are optional and appended to preserve compatibility with
         * all existing constructor calls.
         */
        public ?string $sizeName = null,
        public ?int $vCpu = null,
        public ?int $memoryMiB = null,
        public ?int $diskGiB = null,
        public ?string $taskState = null,
        public ?string $providerError = null,
        public CloudServerPowerState $powerState =
        CloudServerPowerState::Unknown,
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
            array_unique(
                $addresses,
            ),
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

    public function isRunning(): bool
    {
        return $this->powerState->isRunning();
    }

    public function isStopped(): bool
    {
        return $this->powerState->isStopped();
    }

    public function isTransitioning(): bool
    {
        return $this->powerState->isTransitioning();
    }

    public function hasProviderError(): bool
    {
        return is_string(
            $this->providerError,
        ) && trim(
            $this->providerError,
        ) !== '';
    }

    public function hasSizeInformation(): bool
    {
        return is_string(
            $this->sizeId,
        ) && trim(
            $this->sizeId,
        ) !== '';
    }

    public function hasResourceInformation(): bool
    {
        return $this->vCpu !== null
            && $this->memoryMiB !== null
            && $this->diskGiB !== null;
    }

    public function isReadyForSshCheck(): bool
    {
        return $this->status->isReady()
            && $this->powerStateAllowsSshCheck()
            && ! $this->hasProviderError()
            && $this->hasPublicIpv4()
            && is_string(
                $this->username,
            )
            && trim(
                $this->username,
            ) !== '';
    }

    /**
     * Unknown is temporarily accepted for backward compatibility.
     *
     * Once every provider mapper supplies an explicit power state,
     * active SSH operations will only use Running servers.
     */
    private function powerStateAllowsSshCheck(): bool
    {
        return in_array(
            $this->powerState,
            [
                CloudServerPowerState::Running,
                CloudServerPowerState::Unknown,
            ],
            true,
        );
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
            'power_state' => $this->powerState->value,
            'username' => $this->username,

            'size' => [
                'id' => $this->sizeId,
                'name' => $this->sizeName,
                'v_cpu' => $this->vCpu,
                'memory_mib' => $this->memoryMiB,
                'disk_gib' => $this->diskGiB,
            ],

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
            'task_state' => $this->taskState,
            'provider_error' => $this->providerError,
        ];
    }
}
