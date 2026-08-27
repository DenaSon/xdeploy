<?php

declare(strict_types=1);

namespace App\Application\Cloud\Volumes;

use App\Domain\Cloud\Contracts\CloudProviderRegistryInterface;
use App\Domain\Cloud\Contracts\CloudVolumeManagerInterface;
use App\Domain\Cloud\DTOs\CloudVolumeAuditItemData;
use App\Domain\Cloud\DTOs\CloudVolumeData;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Enums\CloudVolumeAuditStatus;
use App\Domain\Cloud\Exceptions\CloudResourceNotFoundException;
use App\Domain\Server\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;

final readonly class ArvanVolumeAuditService
{
    public function __construct(
        private CloudProviderRegistryInterface $providers,
    ) {}

    /** @return list<CloudVolumeAuditItemData> */
    public function audit(): array
    {
        $servers = $this->arvanServers();
        $indexes = $this->serverIndexes($servers);
        $items = [];

        foreach ($this->regions($servers) as $region) {
            foreach ($this->volumeManager()->listVolumes($region) as $volume) {
                $items[] = $this->classify(
                    volume: $volume,
                    serversByProviderKey: $indexes['by_provider'],
                    serversByVolumeKey: $indexes['by_volume'],
                );
            }
        }

        usort(
            $items,
            static fn (
                CloudVolumeAuditItemData $left,
                CloudVolumeAuditItemData $right,
            ): int => [
                $left->regionId,
                $left->volumeName,
                $left->volumeId,
            ] <=> [
                $right->regionId,
                $right->volumeName,
                $right->volumeId,
            ],
        );

        return $items;
    }

    /** @return list<CloudVolumeAuditItemData> */
    public function auditRegion(string $region): array
    {
        $servers = $this->arvanServers();
        $indexes = $this->serverIndexes($servers);
        $items = [];

        foreach ($this->volumeManager()->listVolumes($region) as $volume) {
            $items[] = $this->classify(
                volume: $volume,
                serversByProviderKey: $indexes['by_provider'],
                serversByVolumeKey: $indexes['by_volume'],
            );
        }

        return $items;
    }

    public function find(
        string $region,
        string $volumeId,
    ): ?CloudVolumeAuditItemData {
        foreach ($this->auditRegion($region) as $item) {
            if ($item->volumeId === $volumeId) {
                return $item;
            }
        }

        return null;
    }

    public function findExact(
        string $region,
        string $volumeId,
    ): ?CloudVolumeAuditItemData {
        try {
            $volume = $this->volumeManager()->findVolume(
                region: $region,
                volumeId: $volumeId,
            );
        } catch (CloudResourceNotFoundException) {
            return null;
        }

        $servers = $this->arvanServers();
        $indexes = $this->serverIndexes($servers);

        return $this->classify(
            volume: $volume,
            serversByProviderKey: $indexes['by_provider'],
            serversByVolumeKey: $indexes['by_volume'],
        );
    }

    private function volumeManager(): CloudVolumeManagerInterface
    {
        /** @var CloudVolumeManagerInterface $manager */
        $manager = $this->providers->resolveCapability(
            provider: CloudProviderType::Arvan,
            capability: CloudVolumeManagerInterface::class,
        );

        return $manager;
    }

    /** @return Collection<int, Server> */
    private function arvanServers(): Collection
    {
        return Server::query()
            ->withTrashed()
            ->where('cloud_provider', CloudProviderType::Arvan)
            ->whereNotNull('cloud_server_id')
            ->get();
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return list<string>
     */
    private function regions(Collection $servers): array
    {
        $regions = [];
        $configured = config('cloud.providers.arvan.region');

        if (is_string($configured) && trim($configured) !== '') {
            $regions[trim($configured)] = true;
        }

        foreach ($servers as $server) {
            $region = $server->cloud_region;

            if (is_string($region) && trim($region) !== '') {
                $regions[trim($region)] = true;
            }
        }

        $values = array_keys($regions);
        sort($values);

        return $values;
    }

    /**
     * @param  Collection<int, Server>  $servers
     * @return array{
     *   by_provider: array<string, list<Server>>,
     *   by_volume: array<string, list<Server>>
     * }
     */
    private function serverIndexes(Collection $servers): array
    {
        $byProvider = [];
        $byVolume = [];

        foreach ($servers as $server) {
            $region = is_string($server->cloud_region)
                ? trim($server->cloud_region)
                : '';
            $providerServerId = is_string($server->cloud_server_id)
                ? trim($server->cloud_server_id)
                : '';

            if ($region !== '' && $providerServerId !== '') {
                $byProvider[$this->key($region, $providerServerId)][] = $server;
            }

            if (! is_array($server->termination_volume_ids) || $region === '') {
                continue;
            }

            foreach ($server->termination_volume_ids as $volumeId) {
                if (! is_string($volumeId) || trim($volumeId) === '') {
                    continue;
                }

                $byVolume[$this->key($region, trim($volumeId))][] = $server;
            }
        }

        return [
            'by_provider' => $byProvider,
            'by_volume' => $byVolume,
        ];
    }

    /**
     * @param  array<string, list<Server>>  $serversByProviderKey
     * @param  array<string, list<Server>>  $serversByVolumeKey
     */
    private function classify(
        CloudVolumeData $volume,
        array $serversByProviderKey,
        array $serversByVolumeKey,
    ): CloudVolumeAuditItemData {
        $attachmentIds = [];
        $attachmentNames = [];

        foreach ($volume->attachments as $attachment) {
            $serverId = trim($attachment->serverId);

            if ($serverId === '') {
                continue;
            }

            $attachmentIds[$serverId] = true;

            if (
                is_string($attachment->serverName)
                && trim($attachment->serverName) !== ''
            ) {
                $attachmentNames[$serverId] = trim($attachment->serverName);
            }
        }

        $providerServerIds = array_keys($attachmentIds);

        if (count($providerServerIds) > 1) {
            return $this->item(
                volume: $volume,
                auditStatus: CloudVolumeAuditStatus::Ambiguous,
                attachmentServerId: implode(', ', $providerServerIds),
            );
        }

        if (count($providerServerIds) === 1) {
            $providerServerId = $providerServerIds[0];
            $matches = $serversByProviderKey[
                $this->key($volume->regionId, $providerServerId)
            ] ?? [];

            if (count($matches) === 1) {
                return $this->item(
                    volume: $volume,
                    auditStatus: CloudVolumeAuditStatus::Linked,
                    attachmentServerId: $providerServerId,
                    attachmentServerName: $attachmentNames[$providerServerId] ?? null,
                    server: $matches[0],
                );
            }

            return $this->item(
                volume: $volume,
                auditStatus: CloudVolumeAuditStatus::Ambiguous,
                attachmentServerId: $providerServerId,
                attachmentServerName: $attachmentNames[$providerServerId] ?? null,
            );
        }

        $historicalMatches = $serversByVolumeKey[
            $this->key($volume->regionId, $volume->id)
        ] ?? [];

        if (count($historicalMatches) === 1) {
            return $this->item(
                volume: $volume,
                auditStatus: CloudVolumeAuditStatus::Detached,
                server: $historicalMatches[0],
            );
        }

        if (count($historicalMatches) > 1) {
            return $this->item(
                volume: $volume,
                auditStatus: CloudVolumeAuditStatus::Ambiguous,
            );
        }

        return $this->item(
            volume: $volume,
            auditStatus: CloudVolumeAuditStatus::Orphan,
        );
    }

    private function item(
        CloudVolumeData $volume,
        CloudVolumeAuditStatus $auditStatus,
        ?string $attachmentServerId = null,
        ?string $attachmentServerName = null,
        ?Server $server = null,
    ): CloudVolumeAuditItemData {
        $serverStatus = null;

        if ($server?->status instanceof ServerStatus) {
            $serverStatus = $server->status->value;
        }

        return new CloudVolumeAuditItemData(
            volumeId: $volume->id,
            volumeName: $volume->name,
            regionId: $volume->regionId,
            volumeStatus: $volume->status,
            auditStatus: $auditStatus,
            attachmentServerId: $attachmentServerId,
            attachmentServerName: $attachmentServerName,
            coreflareServerId: $server?->getKey(),
            coreflareServerName: $server?->name,
            coreflareServerStatus: $serverStatus,
            coreflareProviderServerId: is_string($server?->cloud_server_id)
                ? $server->cloud_server_id
                : null,
            coreflareServerDeleted: $server?->trashed() ?? false,
            coreflareServerTerminated: $server?->terminated_at !== null,
        );
    }

    private function key(string $region, string $resourceId): string
    {
        return trim($region).'|'.trim($resourceId);
    }
}
