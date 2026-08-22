<?php

declare(strict_types=1);

namespace App\Infrastructure\Cloud\Transport;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Throwable;

final class CloudProviderRetryPolicy
{
    /**
     * Retry only failures that are plausibly transient and cheap to retry.
     * Interactive timeouts and rate limits are deliberately excluded so a
     * Livewire request is not multiplied into a long-running request.
     */
    public static function shouldRetry(?Throwable $exception): bool
    {
        if ($exception === null) {
            return false;
        }

        if ($exception instanceof ConnectionException) {
            return ! self::isTimeout($exception);
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        return in_array(
            $exception->response->status(),
            [500, 502, 503, 504],
            true,
        );
    }

    private static function isTimeout(ConnectionException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'curl error 28')
            || str_contains($message, 'timed out')
            || str_contains($message, 'timeout');
    }
}
