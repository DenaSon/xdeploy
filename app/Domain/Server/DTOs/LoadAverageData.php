<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class LoadAverageData
{
    public function __construct(
        public float $oneMinute,
        public float $fiveMinutes,
        public float $fifteenMinutes,
    ) {}

    public function toArray(): array
    {
        return [
            'oneMinute' => $this->oneMinute,
            'fiveMinutes' => $this->fiveMinutes,
            'fifteenMinutes' => $this->fifteenMinutes,
        ];
    }
}
