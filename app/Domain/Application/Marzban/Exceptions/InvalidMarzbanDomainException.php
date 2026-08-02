<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

use InvalidArgumentException;

final class InvalidMarzbanDomainException extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self(
            'The Marzban panel domain is invalid.',
        );
    }
}
