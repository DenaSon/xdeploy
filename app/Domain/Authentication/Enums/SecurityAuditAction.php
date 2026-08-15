<?php

declare(strict_types=1);

namespace App\Domain\Authentication\Enums;

enum SecurityAuditAction: string
{
    case PasskeyRegistered = 'passkey_registered';
    case PasskeyVerified = 'passkey_verified';
    case PasskeyDeleted = 'passkey_deleted';
    case AdminPasskeysReset = 'admin_passkeys_reset';
}
