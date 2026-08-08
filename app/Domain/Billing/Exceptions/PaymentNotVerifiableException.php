<?php

declare(strict_types=1);

namespace App\Domain\Billing\Exceptions;

use DomainException;

final class PaymentNotVerifiableException extends DomainException
{
    public static function forPayment(
        int $paymentId,
        string $status,
    ): self {
        return new self(
            "Payment [{$paymentId}] cannot be verified while its status is [{$status}].",
        );
    }

    public static function amountMismatch(
        int $paymentId,
    ): self {
        return new self(
            "Verified amount does not match payment [{$paymentId}].",
        );
    }

    public static function referenceMismatch(
        int $paymentId,
    ): self {
        return new self(
            "Verified reference does not match payment [{$paymentId}].",
        );
    }
}
