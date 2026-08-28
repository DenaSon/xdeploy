<?php

declare(strict_types=1);

namespace App\Application\Support\Events;

final readonly class SupportRequestCreated
{
    public function __construct(
        public int $supportRequestId,
    ) {}
}
