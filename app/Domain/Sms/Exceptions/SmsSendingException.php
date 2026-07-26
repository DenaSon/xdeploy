<?php

declare(strict_types=1);

namespace App\Domain\SMS\Exceptions;

use RuntimeException;

final class SmsSendingException extends RuntimeException
{
    public function __construct(
        string $message = 'Failed to send SMS.',
    ) {
        parent::__construct($message);
    }
}
