<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Enums;

enum CloudBillingPeriod: string
{
    case Hourly = 'hourly';
    case Monthly = 'monthly';
}
