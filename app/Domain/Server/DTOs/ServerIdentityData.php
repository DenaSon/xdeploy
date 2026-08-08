<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class ServerIdentityData
{
    public function __construct(
        public string $hostname,
        public string $operatingSystem,
        public string $kernel,
        public string $uptime,
        public string $user,
        public string $privateIp,
    ) {}

    public function toArray(): array
    {
        return [
            'hostname' => $this->hostname,
            'operatingSystem' => $this->operatingSystem,
            'kernel' => $this->kernel,
            'uptime' => $this->uptime,
            'user' => $this->user,
            'privateIp' => $this->privateIp,
        ];
    }
}
