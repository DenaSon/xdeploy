<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

final class PaymentInitiationInProgressException extends DomainException
{
    public static function forOrder(int $orderId): self
    {
        return new self(
            sprintf(
                'A payment initiation is already in progress for order [%d].',
                $orderId,
            ),
        );
    }
}
