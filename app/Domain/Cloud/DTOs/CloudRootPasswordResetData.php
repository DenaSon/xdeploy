<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudRootPasswordResetData
{
    public function __construct(
        public string $password,
        public string $message,
    ) {}
}
