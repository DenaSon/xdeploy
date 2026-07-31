<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Platform\Enums\PlatformType;
use RuntimeException;

final class PlatformNotRegisteredException extends RuntimeException
{
    public function __construct(
        PlatformType $type,
    ) {
        parent::__construct(
            sprintf(
                'Platform [%s] is not registered.',
                $type->value,
            ),
        );
    }
}
