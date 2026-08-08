<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

final class OrderNotPayableException extends DomainException
{
    public static function forOrder(
        int $orderId,
        string $status,
    ): self {
        return new self(
            "Order [{$orderId}] cannot be paid while its status is [{$status}].",
        );
    }
}
