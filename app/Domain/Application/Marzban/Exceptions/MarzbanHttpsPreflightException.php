<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

use RuntimeException;

final class MarzbanHttpsPreflightException extends RuntimeException
{
    public static function publicAddressUnavailable(): self
    {
        return new self(
            'Unable to determine the target server public IPv4 address.',
        );
    }

    public static function dnsLookupUnavailable(): self
    {
        return new self(
            'Unable to inspect public DNS records for the domain.',
        );
    }

    public static function invalidResponse(): self
    {
        return new self(
            'The Marzban HTTPS preflight response is invalid.',
        );
    }

    public static function serverInspectionUnavailable(): self
    {
        return new self(
            'Unable to inspect the target server ports and Marzban layout.',
        );
    }

    public static function dnsMismatch(): self
    {
        return new self(
            'The domain does not resolve to the target server.',
        );
    }

    /**
     * @param  list<int>  $ports
     */
    public static function portsUnavailable(array $ports): self
    {
        return new self(sprintf(
            'Required ports are unavailable: %s.',
            implode(', ', $ports),
        ));
    }

    public static function unsupportedLayout(): self
    {
        return new self(
            'The current Marzban installation layout is not supported.',
        );
    }

    public static function notReady(): self
    {
        return new self(
            'The Marzban HTTPS preflight is not ready.',
        );
    }
}
