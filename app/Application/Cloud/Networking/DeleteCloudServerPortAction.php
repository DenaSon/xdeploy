<?php

declare(strict_types=1);

namespace App\Application\Cloud\Networking;

use App\Domain\Cloud\Contracts\CloudServerNetworkingInterface;
use App\Domain\Cloud\DTOs\CloudPortData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Cloud\Exceptions\CloudValidationException;
use App\Models\Server;
use App\Models\User;

final readonly class DeleteCloudServerPortAction
{
    public function __construct(
        private CloudServerNetworkingInterface $networking,
    ) {}

    public function handle(
        User $user,
        int $serverId,
        string $portId,
    ): Server {
        $server = $this->ownedServer(
            user: $user,
            serverId: $serverId,
        );

        $region = $this->requiredCloudMetadata(
            server: $server,
            attribute: 'cloud_region',
        );

        $providerServerId = $this->requiredCloudMetadata(
            server: $server,
            attribute: 'cloud_server_id',
        );

        $this->requiredCloudProvider(
            $server,
        );

        $ports = $this->networking->listServerPorts(
            region: $region,
            serverId: $providerServerId,
        );

        $targetPort = $this->findPort(
            ports: $ports,
            portId: $portId,
        );

        $replacementHost = $this->replacementHost(
            ports: $ports,
            excludedPortId: $targetPort->id,
        );

        /*
         * The local host must not be changed unless provider
         * deletion succeeds.
         */
        $this->networking->deletePort(
            region: $region,
            portId: $targetPort->id,
        );

        if (! $this->containsHost(
            port: $targetPort,
            host: $server->host,
        )) {
            return $server->refresh();
        }

        $server->host = $replacementHost;
        $server->save();

        return $server->refresh();
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

    private function requiredCloudProvider(
        Server $server,
    ): CloudProviderType {
        $provider = $server->cloud_provider;

        if (! $provider instanceof CloudProviderType) {
            throw new CloudValidationException(
                'Cloud server metadata is incomplete.',
            );
        }

        return $provider;
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

    /**
     * @param  list<CloudPortData>  $ports
     */
    private function findPort(
        array $ports,
        string $portId,
    ): CloudPortData {
        $portId = trim(
            $portId,
        );

        if ($portId === '') {
            throw new CloudValidationException(
                'Cloud port identifier cannot be empty.',
            );
        }

        foreach ($ports as $port) {
            if ($port->id === $portId) {
                return $port;
            }
        }

        throw new CloudResourceNotFoundException(
            'Cloud server port was not found.',
        );
    }

    private function containsHost(
        CloudPortData $port,
        ?string $host,
    ): bool {
        if (
            ! is_string($host)
            || trim($host) === ''
        ) {
            return false;
        }

        return in_array(
            trim($host),
            $port->ips,
            true,
        );
    }

    /**
     * @param  list<CloudPortData>  $ports
     */
    private function replacementHost(
        array $ports,
        string $excludedPortId,
    ): ?string {
        $ips = [];

        foreach ($ports as $port) {
            if ($port->id === $excludedPortId) {
                continue;
            }

            foreach ($port->ips as $ip) {
                $ip = trim(
                    $ip,
                );

                if (
                    $ip === ''
                    || filter_var(
                        $ip,
                        FILTER_VALIDATE_IP,
                    ) === false
                ) {
                    continue;
                }

                $ips[] = $ip;
            }
        }

        $ips = array_values(
            array_unique($ips),
        );

        foreach ($ips as $ip) {
            if (
                filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4,
                ) !== false
            ) {
                return $ip;
            }
        }

        foreach ($ips as $ip) {
            if (
                filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV6,
                ) !== false
            ) {
                return $ip;
            }
        }

        return null;
    }
}
