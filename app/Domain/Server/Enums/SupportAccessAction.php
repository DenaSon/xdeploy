<?php

declare(strict_types=1);

namespace App\Domain\Server\Enums;

enum SupportAccessAction: string
{
    case SshConnectionTest = 'ssh_connection_test';
    case PasskeyConfirmed = 'passkey_confirmed';
    case CredentialRevealed = 'credential_revealed';
}
