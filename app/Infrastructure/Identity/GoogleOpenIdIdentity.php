<?php

declare(strict_types=1);

namespace App\Infrastructure\Identity;

final readonly class GoogleOpenIdIdentity
{
    public function __construct(
        public string $subject,
        public string $email,
        public bool $emailVerified,
    ) {}
}
