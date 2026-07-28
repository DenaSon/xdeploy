<?php

declare(strict_types=1);

namespace App\Domain\Authentication\DTOs;

use App\Domain\Authentication\ValueObjects\OtpCode;
use App\Domain\User\ValueObjects\PhoneNumber;

final readonly class VerifyOtpData
{
    public function __construct(
        public PhoneNumber $phone,
        public OtpCode $code,
    ) {}

    public static function from(
        string $phone,
        string $code,
    ): self {
        return new self(
            phone: PhoneNumber::from($phone),
            code: OtpCode::from($code),
        );
    }
}
