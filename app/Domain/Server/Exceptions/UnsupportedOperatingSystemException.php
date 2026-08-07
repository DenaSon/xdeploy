<?php

declare(strict_types=1);

namespace App\Domain\Server\Exceptions;

use App\Domain\Server\DTOs\OperatingSystemInfo;
use RuntimeException;

final class UnsupportedOperatingSystemException extends RuntimeException
{
    public function __construct(
        public readonly OperatingSystemInfo $operatingSystem,
    ) {
        parent::__construct(
            sprintf(
                'Operating system [%s] is not supported by xDeploy.',
                $operatingSystem->displayName(),
            ),
        );
    }
}
