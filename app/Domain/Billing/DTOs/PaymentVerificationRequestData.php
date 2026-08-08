<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class PaymentVerificationRequestData
{
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
    ) {}
}
