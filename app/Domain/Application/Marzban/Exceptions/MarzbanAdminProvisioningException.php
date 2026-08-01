<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

class MarzbanAdminProvisioningException extends MarzbanManagementException
{
    public static function commandFailed(): self
    {
        return new self(
            'Marzban admin creation failed.',
        );
    }

    public static function inspectionFailed(): self
    {
        return new self(
            'Marzban setup could not be inspected before admin creation.',
        );
    }

    public static function verificationFailed(): self
    {
        return new self(
            'The Marzban sudo admin could not be verified after creation.',
        );
    }
}
