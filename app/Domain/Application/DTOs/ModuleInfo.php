<?php

declare(strict_types=1);

namespace App\Domain\Application\DTOs;

use App\Domain\Application\Enums\ModuleState;
use App\Domain\Application\ValueObjects\ModuleDependency;
use App\Domain\Application\ValueObjects\ProvidedSoftware;

final readonly class ModuleInfo
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ModuleDependency>  $dependencies
     * @param  list<ProvidedSoftware>  $provides
     */
    public function __construct(
        public ModuleState $state,
        public array $metadata = [],
        public array $dependencies = [],
        public array $provides = [],
    ) {}

    public function isRunning(): bool
    {
        return $this->state === ModuleState::Running;
    }

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
        return $this->version() !== null;
    }
}
