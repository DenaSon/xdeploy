<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class SystemServiceData
{
    public function __construct(
        public string $unit,
        public string $name,
        public string $loadState,
        public string $activeState,
        public string $subState,
        public string $description,
    ) {}

    public function isActive(): bool
    {
        return $this->activeState === 'active';
    }

    public function isFailed(): bool
    {
        return $this->activeState === 'failed'
            || $this->subState === 'failed';
    }

    /**
     * Normalize systemd states for presentation.
     *
     * Raw states remain available through activeState/subState.
     */
    public function status(): string
    {
        if ($this->isFailed()) {
            return 'failed';
        }

        return match ($this->activeState) {
            'active' => 'active',
            'activating' => 'starting',
            'deactivating' => 'stopping',
            'reloading' => 'reloading',
            'inactive' => 'inactive',
            default => 'unknown',
        };
    }

    /**
     * @return array{
     *     unit: string,
     *     name: string,
     *     status: string,
     *     load_state: string,
     *     active_state: string,
     *     sub_state: string,
     *     description: string
     * }
     */
    public function toArray(): array
    {
        return [
            'unit' => $this->unit,
            'name' => $this->name,
            'status' => $this->status(),
            'load_state' => $this->loadState,
            'active_state' => $this->activeState,
            'sub_state' => $this->subState,
            'description' => $this->description,
        ];
    }
}
