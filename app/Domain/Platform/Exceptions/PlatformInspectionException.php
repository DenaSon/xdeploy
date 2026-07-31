<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Platform\Enums\PlatformType;
use RuntimeException;

final class PlatformInspectionException extends RuntimeException
{
    public function __construct(
        PlatformType $type,
    ) {
        parent::__construct(
            sprintf(
                'Unable to determine the current state of platform [%s].',
                $type->value,
            ),
        );
    }
}
