<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudServerActionData
{
    public function __construct(
        public string $action,
        public ?string $message = null,
        public ?string $startedAt = null,
    ) {}
}
