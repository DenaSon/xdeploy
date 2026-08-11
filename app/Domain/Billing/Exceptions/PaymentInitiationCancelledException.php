<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

final class PaymentInitiationCancelledException extends DomainException
{
    public static function forPayment(int $paymentId): self
    {
        return new self(
            "Payment [{$paymentId}] was cancelled before gateway initiation completed.",
        );
    }
}
