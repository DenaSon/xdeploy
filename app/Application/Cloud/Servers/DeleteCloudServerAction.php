<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Exceptions\CloudValidationException;
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
         * This action is an internal Cloud lifecycle operation.
         *
         * User-facing deletion is intentionally blocked for
         * cloud-provisioned servers by Server\DeleteServerAction
         * and by the Servers Index UI.
         *
         * If Cloud deletion is invoked by a trusted workflow,
         * the Provider must succeed before the local record is
         * soft-deleted.
         */
        $this->lifecycle->deleteServer(
            region: $cloudRegion,
            serverId: $cloudServerId,
        );

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
