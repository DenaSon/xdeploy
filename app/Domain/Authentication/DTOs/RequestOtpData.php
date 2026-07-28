<?php

declare(strict_types=1);

namespace App\Domain\Authentication\DTOs;

use App\Domain\User\ValueObjects\PhoneNumber;

final readonly class RequestOtpData
{
    public function __construct(
        public PhoneNumber $phone,
    ) {}

    public static function from(string $phone): self
    {
        return new self(
            phone: PhoneNumber::from($phone),
        );
    }
}
