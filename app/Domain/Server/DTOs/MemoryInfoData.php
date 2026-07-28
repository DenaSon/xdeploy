<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

use App\Support\Formatters\ByteFormatter;

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
            'total' => ByteFormatter::format($this->total),
            'used' => ByteFormatter::format($this->used),
            'free' => ByteFormatter::format($this->free),
            'available' => ByteFormatter::format($this->available),

            'usagePercent' => $this->usagePercent,
        ];
    }
}
