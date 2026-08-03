<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudBillingPeriod;

final readonly class CloudPriceData
{
    public function __construct(
        public string $amount,
        public ?string $currencyCode,
        public CloudBillingPeriod $billingPeriod,
    ) {}
}
