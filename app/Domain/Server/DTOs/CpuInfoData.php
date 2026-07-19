<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class CpuInfoData
{
    public function __construct(
        public string $architecture,
        public string $model,
        public int $cores,
        public int $threads,
    ) {}

    public function toArray(): array
    {
        return [
            'architecture' => $this->architecture,
            'model' => $this->model,
            'cores' => $this->cores,
            'threads' => $this->threads,
        ];
    }
}
