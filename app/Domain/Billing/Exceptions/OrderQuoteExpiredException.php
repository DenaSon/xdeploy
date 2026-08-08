<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

final class OrderQuoteExpiredException extends DomainException
{
    public static function forOrder(int $orderId): self
    {
        return new self(
            "The price quote for order [{$orderId}] has expired.",
        );
    }
}
