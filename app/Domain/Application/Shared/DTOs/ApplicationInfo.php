<?php

declare(strict_types=1);

namespace App\Domain\Application\Shared\DTOs;

use App\Domain\Application\Shared\Enums\ApplicationState;
use App\Domain\Application\Shared\ValueObjects\ApplicationRequirements;
use App\Domain\Application\Shared\ValueObjects\ProvidedSoftware;

final readonly class ApplicationInfo
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ProvidedSoftware>  $provides
     */
    public function __construct(
        public ApplicationState $state,
        public array $metadata = [],
        public ApplicationRequirements $requirements = new ApplicationRequirements,
        public array $provides = [],
    ) {}

    public function isRunning(): bool
    {
        return $this->state->isRunning();
    }

    public function isInstalled(): bool
    {
        return $this->state->isInstalled();
    }

    public function isNotInstalled(): bool
    {
        return $this->state->isNotInstalled();
    }

    public function isUnknown(): bool
    {
        return $this->state->isUnknown();
    }

    public function version(): ?string
    {
        $version = $this->metadata['version'] ?? null;

        return is_string($version)
            ? $version
            : null;
    }

    public function hasVersion(): bool
    {
        return $this->version() !== null;
    }
}
