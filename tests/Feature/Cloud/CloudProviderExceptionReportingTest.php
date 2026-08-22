<?php

declare(strict_types=1);

namespace Tests\Feature\Cloud;

use App\Domain\Cloud\Exceptions\CloudAuthenticationException;
use App\Domain\Cloud\Exceptions\CloudAuthorizationException;
use App\Domain\Cloud\Exceptions\CloudConfigurationException;
use App\Domain\Cloud\Exceptions\CloudConnectionException;
use App\Domain\Cloud\Exceptions\CloudInsufficientBalanceException;
use App\Domain\Cloud\Exceptions\CloudRateLimitException;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudUnexpectedResponseException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use RuntimeException;
use Tests\TestCase;

final class CloudProviderExceptionReportingTest extends TestCase
{
    public function test_observed_provider_http_failures_are_not_reported_twice(): void
    {
        $handler = app(ExceptionHandler::class);

        foreach (
            [
                new CloudAuthenticationException,
                new CloudAuthorizationException,
                new CloudConnectionException,
                new CloudInsufficientBalanceException,
                new CloudRateLimitException,
                new CloudResourceNotFoundException,
            ] as $exception
        ) {
            $this->assertFalse(
                $handler->shouldReport($exception),
                sprintf(
                    '%s should be handled by provider HTTP observability.',
                    $exception::class,
                ),
            );
        }
    }

    public function test_unobserved_or_unexpected_failures_remain_reportable(): void
    {
        $handler = app(ExceptionHandler::class);

        foreach (
            [
                new CloudConfigurationException,
                new CloudValidationException,
                new CloudUnexpectedResponseException,
                new RuntimeException('Unexpected failure.'),
            ] as $exception
        ) {
            $this->assertTrue(
                $handler->shouldReport($exception),
                sprintf(
                    '%s should remain reportable.',
                    $exception::class,
                ),
            );
        }
    }
}
