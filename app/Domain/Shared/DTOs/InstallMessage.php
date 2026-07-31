<?php

declare(strict_types=1);

namespace App\Domain\Shared\DTOs;

final readonly class InstallMessage
{
    public function __construct(
        public string $component,
        public string $message,
    ) {}
}
