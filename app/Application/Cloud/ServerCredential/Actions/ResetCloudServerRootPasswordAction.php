<?php

declare(strict_types=1);

namespace App\Application\Cloud\ServerCredential\Actions;

use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Domain\Cloud\Contracts\CloudServerCredentialManagerInterface;
use App\Domain\Cloud\DTOs\CloudRootPasswordResetData;
use App\Models\Server;

final readonly class ResetCloudServerRootPasswordAction
{
    public function __construct(
        private CloudServerCapabilityResolver $capabilities,
    ) {}

    public function handle(Server $server): CloudRootPasswordResetData
    {
        [$target, $credentialManager] = $this->capabilities->resolve(
            server: $server,
            capability: CloudServerCredentialManagerInterface::class,
        );

        return $credentialManager->resetRootPassword(
            region: $target->region,
            serverId: $target->serverId,
        );
    }
}
