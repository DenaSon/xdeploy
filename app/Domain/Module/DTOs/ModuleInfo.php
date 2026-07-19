<?php

declare(strict_types=1);

namespace App\Domain\Module\DTOs;

use App\Domain\Module\Enums\ModuleState;

final readonly class ModuleInfo
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ModuleState $state,
        public array $metadata = [],
    ) {}

    public function isInstalled(): bool
    {
        return $this->state === ModuleState::Installed;
    }

    public function isNotInstalled(): bool
    {
        return $this->state === ModuleState::NotInstalled;
    }

    public function version(): ?string
    {
        return $this->metadata['version'] ?? null;
    }

    public function hasVersion(): bool
    {
        return isset($this->metadata['version']);
    }

}
