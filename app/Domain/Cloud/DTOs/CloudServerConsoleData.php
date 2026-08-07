<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudServerConsoleData
{
    public function __construct(
        public string $url,
    ) {}
}
