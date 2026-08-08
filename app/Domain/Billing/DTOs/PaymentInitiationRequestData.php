<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class PaymentInitiationRequestData
{
    public function __construct(
        public int $orderId,
        public int $amount,
        public string $currency,
        public string $callbackUrl,
        public string $description,
    ) {}
}
