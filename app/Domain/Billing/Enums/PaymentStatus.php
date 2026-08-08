<?php

declare(strict_types=1);

namespace App\Domain\Billing\Enums;

enum PaymentStatus: string
{
    case Initiating = 'initiating';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
