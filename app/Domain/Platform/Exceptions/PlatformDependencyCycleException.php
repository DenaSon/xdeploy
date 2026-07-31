<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Platform\Enums\PlatformType;
use RuntimeException;

final class PlatformDependencyCycleException extends RuntimeException
{
    /**
     * @param  list<PlatformType>  $path
     */
    public function __construct(
        array $path,
    ) {
        parent::__construct(
            sprintf(
                'Platform dependency cycle detected: %s.',
                implode(
                    ' -> ',
                    array_map(
                        static fn (PlatformType $type): string => $type->value,
                        $path,
                    ),
                ),
            ),
        );
    }
}
