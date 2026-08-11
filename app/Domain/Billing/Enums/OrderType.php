<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum OrderType: string
{
    case Provisioning = 'provisioning';
    case Renewal = 'renewal';
}
