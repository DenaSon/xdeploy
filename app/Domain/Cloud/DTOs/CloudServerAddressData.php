<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudIpVersion;
use InvalidArgumentException;

final readonly class CloudServerAddressData
{
    public function __construct(
        public string $address,
        public CloudIpVersion $version,
        public bool $isPublic,
        public bool $isVpc,
        public ?string $type = null,
    ) {
        $validationFlag = match ($this->version) {
            CloudIpVersion::IPv4 => FILTER_FLAG_IPV4,
            CloudIpVersion::IPv6 => FILTER_FLAG_IPV6,
        };

        if (
            filter_var(
                $this->address,
                FILTER_VALIDATE_IP,
                $validationFlag,
            ) === false
        ) {
            throw new InvalidArgumentException(
                'Cloud server address is invalid.',
            );
        }
    }

    public function isPublicIpv4(): bool
    {
        return $this->isPublic
            && $this->version === CloudIpVersion::IPv4;
    }

    public function isPublicIpv6(): bool
    {
        return $this->isPublic
            && $this->version === CloudIpVersion::IPv6;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'version' => $this->version->value,
            'is_public' => $this->isPublic,
            'is_vpc' => $this->isVpc,
            'type' => $this->type,
        ];
    }
}
