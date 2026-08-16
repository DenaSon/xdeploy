<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Contracts;

use App\Models\Server;
use SensitiveParameter;

interface SSHCredentialVerifierInterface
{
    public function verifyCredential(
        Server $server,
        #[SensitiveParameter]
        string $password,
    ): void;
}
