<?php

declare(strict_types=1);

namespace App\Events;

final readonly class SystemSettingsUpdated
{
    /**
     * @param  list<string>  $changedKeys
     */
    public function __construct(
        public int $actorId,
        public string $group,
        public array $changedKeys,
    ) {}
}
