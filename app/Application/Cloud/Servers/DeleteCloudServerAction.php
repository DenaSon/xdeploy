<?php

declare(strict_types=1);

namespace App\Application\Cloud\Servers;

use App\Application\Server\Actions\DeleteServerAction;
use App\Domain\Cloud\Contracts\CloudServerLifecycleInterface;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Models\Server;
use App\Models\User;

final readonly class DeleteCloudServerAction
{
    public function __construct(
        private CloudServerLifecycleInterface $lifecycle,
        private DeleteServerAction $deleteServer,
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
         * Provider deletion must succeed before the local record
         * is removed. On provider failure, xDeploy keeps the
         * record for retry or manual recovery.
         */
        $this->lifecycle->deleteServer(
            region: $cloudRegion,
            serverId: $cloudServerId,
        );

        $this->deleteServer->handle(
            user: $user,
            serverId: $serverId,
        );
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
