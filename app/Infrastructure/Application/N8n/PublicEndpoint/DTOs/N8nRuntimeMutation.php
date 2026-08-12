<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\N8n\PublicEndpoint\DTOs;

final readonly class N8nRuntimeMutation
{
    public function __construct(
        public ?string $backupToken,
        public bool $configurationChanged,
    ) {}
}
