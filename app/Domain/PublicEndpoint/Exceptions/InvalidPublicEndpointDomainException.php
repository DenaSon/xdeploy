<?php

declare(strict_types=1);

namespace App\Domain\PublicEndpoint\Exceptions;

use InvalidArgumentException;

final class InvalidPublicEndpointDomainException extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self(
            'The public endpoint domain is invalid.',
        );
    }
}
