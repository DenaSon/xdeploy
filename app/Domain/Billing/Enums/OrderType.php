<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum OrderType: string
{
    case CloudPurchase = 'cloud_purchase';
    case CloudRenewal = 'cloud_renewal';
}
