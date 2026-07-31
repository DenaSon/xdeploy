<?php

declare(strict_types=1);

namespace App\Domain\Platform\DTOs;

use App\Domain\Platform\Enums\PlatformState;

final readonly class PlatformInfo
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public PlatformState $state,
        public array $metadata = [],
    ) {}

    public function isRunning(): bool
    {
        return $this->state === PlatformState::Running;
    }

    public function isInstalled(): bool
    {
        return $this->state === PlatformState::Installed
            || $this->state === PlatformState::Running;
    }

    public function isNotInstalled(): bool
    {
        return $this->state === PlatformState::NotInstalled;
    }

    public function isUnknown(): bool
    {
        return $this->state === PlatformState::Unknown;
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
