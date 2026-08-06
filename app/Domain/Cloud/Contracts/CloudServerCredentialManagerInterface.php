<?php

declare(strict_types=1);

namespace App\Domain\Cloud\Contracts;

use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;

interface CloudServerCredentialManagerInterface
{
    public function resetRootPassword(
        string $region,
        string $serverId,
    ): CloudRootPasswordResetData;
}
