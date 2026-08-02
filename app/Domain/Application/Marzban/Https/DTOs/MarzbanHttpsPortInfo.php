<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Https\DTOs;

use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;

final readonly class MarzbanHttpsPortInfo
{
    public function __construct(
        public int $port,
        public MarzbanHttpsPortState $state,
        public MarzbanHttpsPortOwner $owner,
    ) {}

    public function availableForXDeploy(): bool
    {
        return in_array(
            needle: $this->state,
            haystack: [
                MarzbanHttpsPortState::Available,
                MarzbanHttpsPortState::Managed,
            ],
            strict: true,
        );
    }

    public function hasConflict(): bool
    {
        return $this->state === MarzbanHttpsPortState::Conflict;
    }

    /**
     * @return array{
     *     port: int,
     *     state: string,
     *     owner: string,
     *     available_for_xdeploy: bool,
     *     has_conflict: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'port' => $this->port,
            'state' => $this->state->value,
            'owner' => $this->owner->value,
            'available_for_xdeploy' => $this->availableForXDeploy(),
            'has_conflict' => $this->hasConflict(),
        ];
    }
}
