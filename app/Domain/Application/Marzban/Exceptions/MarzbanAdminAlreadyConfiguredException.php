<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

final class MarzbanAdminAlreadyConfiguredException extends MarzbanAdminProvisioningException
{
    public static function make(): self
    {
        return new self(
            'A Marzban sudo admin is already configured.',
        );
    }
}
