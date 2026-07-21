<?php
declare(strict_types=1);

namespace App\Domain\Module\DTOs;

final readonly class InstallMessage
{
    public function __construct(
        public string $module,
        public string $message,
    ) {}
}
