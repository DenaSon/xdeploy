<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use InvalidArgumentException;

final class InvalidPublicDomainNameException extends InvalidArgumentException
{
    public static function make(): self
    {
        return new self('Public domain name is invalid.');
    }
}
