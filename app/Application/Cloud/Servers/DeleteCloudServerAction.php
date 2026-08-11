<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use App\Models\User;

final readonly class DeleteCloudServerAction
{
    public function __construct(
        private CloudServerLifecycleInterface $lifecycle,
    ) {}

    public function handle(
        User $user,
        int $serverId,
    ): void {
        $server = $this->ownedServer(
            user: $user,
            serverId: $serverId,
        );

        $cloudRegion = $this->requiredCloudMetadata(
            server: $server,
            attribute: 'cloud_region',
        );

        $cloudServerId = $this->requiredCloudMetadata(
            server: $server,
            attribute: 'cloud_server_id',
        );

        $this->requiredCloudMetadata(
            server: $server,
            attribute: 'cloud_provider',
        );

        /*
         * Provider deletion is the authoritative external side effect.
         *
         * "Not found" is also a successful terminal state: a previous
         * DELETE may already have removed the resource before xDeploy
         * persisted its local completion state.
         */
        try {
            $this->lifecycle->deleteServer(
                region: $cloudRegion,
                serverId: $cloudServerId,
            );
        } catch (CloudResourceNotFoundException) {
            // Desired state already reached at the provider.
        }

        /*
         * Persist termination metadata before the local soft delete.
         * If this persistence fails, a later retry can safely call the
         * provider again; provider "not found" remains idempotent.
         */
        $server->forceFill([
            'status' => ServerStatus::Inactive,
            'terminated_at' => $server->terminated_at
                ?? now(),
            'termination_last_error' => null,
        ])->saveOrFail();

        $server->delete();
    }

    private function ownedServer(
        User $user,
        int $serverId,
    ): Server {
        return $user
            ->servers()
            ->whereKey($serverId)
            ->firstOrFail();
    }

    private function requiredCloudMetadata(
        Server $server,
        string $attribute,
    ): string {
        $value = $server->getAttribute(
            $attribute,
        );

        if (
            ! is_string($value)
            || trim($value) === ''
        ) {
            throw new CloudValidationException(
                'Cloud server metadata is incomplete.',
            );
        }

        return trim(
            $value,
        );
    }
}
