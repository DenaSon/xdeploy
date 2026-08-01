<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Exceptions;

use App\Domain\Server\Enums\AuthenticationType;
use RuntimeException;

final class UnsupportedAuthenticationTypeException extends RuntimeException
{
    public static function forType(
        AuthenticationType $type,
    ): self {
        return new self(
            sprintf(
                'SSH authentication type [%s] is not supported.',
                $type->value,
            ),
        );
    }
}
