<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\DTOs;

use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\ValueObjects\ApplicationDependency;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;

final readonly class ApplicationInfo
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ApplicationDependency>  $dependencies
     * @param  list<ProvidedSoftware>  $provides
     */
    public function __construct(
        public ApplicationState $state,
        public array            $metadata = [],
        public array            $dependencies = [],
        public array            $provides = [],
    ) {}

    public function isRunning(): bool
    {
        return $this->state === ApplicationState::Running;
    }

    public function isInstalled(): bool
    {
        return $this->state === ApplicationState::Installed;
    }

    public function isNotInstalled(): bool
    {
        return $this->state === ApplicationState::NotInstalled;
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
