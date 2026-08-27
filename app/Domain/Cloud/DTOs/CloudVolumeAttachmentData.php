<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use DateTimeImmutable;

final readonly class CloudVolumeAttachmentData
{
    public function __construct(
        public ?string $id,
        public string $serverId,
        public ?string $serverName = null,
        public ?string $device = null,
        public ?DateTimeImmutable $attachedAt = null,
        public ?string $hostName = null,
    ) {}
}
