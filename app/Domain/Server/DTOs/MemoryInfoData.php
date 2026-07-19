<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class MemoryInfoData
{
    public function __construct(
        public int $total,
        public int $used,
        public int $free,
        public int $available,
        public int $usagePercent,
    ) {}

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'used' => $this->used,
            'free' => $this->free,
            'available' => $this->available,
            'usage_percent' => $this->usagePercent,
        ];
    }
}
