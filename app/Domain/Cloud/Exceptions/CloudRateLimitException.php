<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Exceptions;

use Throwable;

final class CloudRateLimitException extends CloudProviderException
{
    public function __construct(
        string $message = 'Cloud provider rate limit exceeded.',
        public readonly ?int $retryAfterSeconds = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            code: $code,
            previous: $previous,
        );
    }
}
