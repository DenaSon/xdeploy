<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

use RuntimeException;

final class MarzbanHttpsInspectionException extends RuntimeException
{
    public static function failed(): self
    {
        return new self(
            'Unable to inspect the Marzban HTTPS configuration.',
        );
    }
}
