<?php

declare(strict_types=1);

namespace App\Domain\Support\Exceptions;

use RuntimeException;

final class SupportRequestClosedException extends RuntimeException
{
    public static function forRequest(int $supportRequestId): self
    {
        return new self(
            sprintf(
                'Support request [%d] is closed and cannot receive new messages.',
                $supportRequestId,
            ),
        );
    }
}
