<?php

declare(strict_types=1);

namespace App\Domain\Application\Marzban\Exceptions;

final class MarzbanSetupInspectionException extends MarzbanManagementException
{
    public static function failed(): self
    {
        return new self(
            'Unable to inspect the Marzban setup state.',
        );
    }
}
