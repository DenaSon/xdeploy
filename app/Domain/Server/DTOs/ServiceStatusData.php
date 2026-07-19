<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class ServiceStatusData
{
    public function __construct(
        public string $name,
        public string $status,
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
