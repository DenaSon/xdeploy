<?php

declare(strict_types=1);

namespace App\Domain\Billing\DTOs;

final readonly class PurchaseQuoteExpectationData
{
    public function __construct(
        public int $finalAmount,
        public string $currency,
        public int $durationHours,
        public int $selectedDiskGiB,
    ) {}
}
