<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Https\DTOs;

final readonly class MarzbanHttpsRuntimeMutation
{
    public function __construct(
        public string $backupToken,
        public bool $configurationChanged,
    ) {}
}
