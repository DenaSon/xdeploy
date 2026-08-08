<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment\ZarinPal;

use RuntimeException;

final class ZarinPalPaymentException extends RuntimeException
{
    public static function initiationFailed(
        int $code = 0,
    ): self {
        return new self(
            "ZarinPal payment initiation failed with code [{$code}].",
            $code,
        );
    }

    public static function verificationFailed(
        int $code = 0,
    ): self {
        return new self(
            "ZarinPal payment verification failed with code [{$code}].",
            $code,
        );
    }

    public static function invalidResponse(
        string $operation,
    ): self {
        return new self(
            "ZarinPal returned an invalid response for [{$operation}].",
        );
    }
}
