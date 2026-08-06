<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerCredential\Actions;

use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;

final readonly class ResetCloudServerRootPasswordAction
{
    public function __construct(
        private CloudServerCredentialManagerInterface $credentialManager,
    ) {}

    public function handle(
        string $region,
        string $serverId,
    ): CloudRootPasswordResetData {
        return $this->credentialManager->resetRootPassword(
            region: $region,
            serverId: $serverId,
        );
    }
}
