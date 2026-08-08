<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class PaymentInitiationData
{
    public function __construct(
        public string $reference,
        public string $redirectUrl,
    ) {}
}
