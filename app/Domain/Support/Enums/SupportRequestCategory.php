<?php

declare(strict_types=1);

namespace App\Domain\Support\Enums;

enum SupportRequestCategory: string
{
    case Technical = 'technical';
    case Billing = 'billing';
    case Account = 'account';
    case Other = 'other';
}
