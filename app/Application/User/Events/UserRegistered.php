<?php

declare(strict_types=1);

namespace App\Application\User\Events;

final readonly class UserRegistered
{
    public function __construct(
        public int $userId,
    ) {}
}
