<?php

declare(strict_types=1);

namespace App\Domain\Platform\Caddy\Sites\Exceptions;

use RuntimeException;

final class CaddySiteInspectionException extends RuntimeException
{
    public static function failed(): self
    {
        return new self(
            'The xDeploy-managed Caddy site state could not be inspected safely.',
        );
    }
}
