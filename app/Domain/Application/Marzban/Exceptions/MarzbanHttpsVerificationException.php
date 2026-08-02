<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

use RuntimeException;

final class MarzbanHttpsVerificationException extends RuntimeException
{
    public static function serviceUnavailable(): self
    {
        return new self(
            'The Marzban HTTPS endpoint is unavailable.',
        );
    }

    public static function certificateUnavailable(): self
    {
        return new self(
            'A trusted TLS certificate is not available for the domain.',
        );
    }

    public static function stateMismatch(): self
    {
        return new self(
            'The resulting Marzban HTTPS state does not match the requested configuration.',
        );
    }
}
