<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use DateTimeImmutable;

final readonly class CloudVolumeData
{
    /**
     * @param  list<CloudVolumeAttachmentData>  $attachments
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public string $status,
        public array $attachments = [],
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public function isAttached(): bool
    {
        return $this->attachments !== [];
    }

    public function isDetached(): bool
    {
        return ! $this->isAttached();
    }

    public function isAttachedTo(string $serverId): bool
    {
        $serverId = trim($serverId);

        if ($serverId === '') {
            return false;
        }

        foreach ($this->attachments as $attachment) {
            if ($attachment->serverId === $serverId) {
                return true;
            }
        }

        return false;
    }
}
