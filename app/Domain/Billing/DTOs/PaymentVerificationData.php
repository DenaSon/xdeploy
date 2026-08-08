<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

use DateTimeImmutable;

final readonly class PaymentVerificationData
{
    public function __construct(
        public string $reference,
        public string $transactionId,
        public int $amount,
        public DateTimeImmutable $verifiedAt,
    ) {}
}
