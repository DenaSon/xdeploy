<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use App\Domain\Cloud\Enums\CloudVolumeAuditStatus;

final readonly class CloudVolumeAuditItemData
{
    public function __construct(
        public string $volumeId,
        public string $volumeName,
        public string $regionId,
        public string $volumeStatus,
        public CloudVolumeAuditStatus $auditStatus,
        public ?string $attachmentServerId = null,
        public ?string $attachmentServerName = null,
        public ?int $coreflareServerId = null,
        public ?string $coreflareServerName = null,
        public ?string $coreflareServerStatus = null,
        public ?string $coreflareProviderServerId = null,
        public bool $coreflareServerDeleted = false,
        public bool $coreflareServerTerminated = false,
    ) {}

    public function canDelete(): bool
    {
        if (! in_array(
            $this->auditStatus,
            [
                CloudVolumeAuditStatus::Detached,
                CloudVolumeAuditStatus::Orphan,
            ],
            true,
        )) {
            return false;
        }

        if ($this->attachmentServerId !== null) {
            return false;
        }

        return ! in_array(
            strtolower(trim($this->volumeStatus)),
            ['deleting', 'deleted'],
            true,
        );
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'volume_id' => $this->volumeId,
            'volume_name' => $this->volumeName,
            'region_id' => $this->regionId,
            'volume_status' => $this->volumeStatus,
            'audit_status' => $this->auditStatus->value,
            'attachment_server_id' => $this->attachmentServerId,
            'attachment_server_name' => $this->attachmentServerName,
            'coreflare_server_id' => $this->coreflareServerId,
            'coreflare_server_name' => $this->coreflareServerName,
            'coreflare_server_status' => $this->coreflareServerStatus,
            'coreflare_provider_server_id' => $this->coreflareProviderServerId,
            'coreflare_server_deleted' => $this->coreflareServerDeleted,
            'coreflare_server_terminated' => $this->coreflareServerTerminated,
            'can_delete' => $this->canDelete(),
        ];
    }
}
