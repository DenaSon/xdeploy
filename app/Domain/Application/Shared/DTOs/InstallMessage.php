<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\DTOs;

final readonly class InstallMessage
{
    public function __construct(
        public string $application,
        public string $message,
    ) {}
}
