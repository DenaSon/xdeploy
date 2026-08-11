<?php

declare(strict_types=1);

namespace App\Infrastructure\Sms\Exceptions;

use RuntimeException;
use Throwable;

final class SmsSendingException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to send SMS.',
        private readonly bool $retryable = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    public static function permanent(
        string $message,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: $message,
            retryable: false,
            previous: $previous,
        );
    }

    public static function transient(
        string $message,
        ?Throwable $previous = null,
    ): self {
        return new self(
            message: $message,
            retryable: true,
            previous: $previous,
        );
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }
}
