<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Provisioning = 'provisioning';
    case Fulfilled = 'fulfilled';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
