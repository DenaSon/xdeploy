<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Payment\ZarinPal;

use App\Infrastructure\Payment\ZarinPal\ZarinPalPaymentException;
use PHPUnit\Framework\TestCase;

final class ZarinPalPaymentExceptionTest extends TestCase
{
    public function test_temporary_provider_failures_are_retryable(): void
    {
        foreach ([0, 408, 429, 500, 503] as $code) {
            $exception = new ZarinPalPaymentException(
                'Temporary provider failure.',
                $code,
            );

            $this->assertTrue(
                $exception->isRetryable(),
                "Expected code [{$code}] to be retryable.",
            );
        }
    }

    public function test_business_failures_are_not_retryable(): void
    {
        foreach ([-51, 400, 401, 422] as $code) {
            $exception = new ZarinPalPaymentException(
                'Non-retryable provider failure.',
                $code,
            );

            $this->assertFalse(
                $exception->isRetryable(),
                "Expected code [{$code}] to be non-retryable.",
            );
        }
    }
}
