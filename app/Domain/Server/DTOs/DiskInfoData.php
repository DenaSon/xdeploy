<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class DiskInfoData
{
    public function __construct(
        public int $total,
        public int $used,
        public int $available,
        public int $usagePercent,
    ) {}

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'used' => $this->used,
            'available' => $this->available,
            'usage_percent' => $this->usagePercent,
        ];
    }
}
