<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers\Termination;

final readonly class CloudServerTerminationDecision
{
    /**
     * @param  array<string, bool|int|float|string|null>  $context
     */
    public function __construct(
        public CloudServerTerminationState $state,
        public array $context = [],
    ) {}

    public function readyForDelete(): bool
    {
        return $this->state->isReadyForDelete();
    }
}
