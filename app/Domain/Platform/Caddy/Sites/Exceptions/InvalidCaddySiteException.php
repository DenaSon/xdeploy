<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\Exceptions;

use InvalidArgumentException;

final class InvalidCaddySiteException extends InvalidArgumentException
{
    public static function key(): self
    {
        return new self(
            'Invalid Caddy site key.',
        );
    }

    public static function domain(): self
    {
        return new self(
            'Invalid Caddy site domain.',
        );
    }

    public static function upstream(): self
    {
        return new self(
            'Invalid Caddy site upstream.',
        );
    }
}
