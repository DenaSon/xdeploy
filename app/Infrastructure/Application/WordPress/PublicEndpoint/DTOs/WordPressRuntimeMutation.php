<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\WordPress\PublicEndpoint\DTOs;

final readonly class WordPressRuntimeMutation
{
    public function __construct(
        public ?string $backupToken,
        public bool $configurationChanged,
    ) {}
}
