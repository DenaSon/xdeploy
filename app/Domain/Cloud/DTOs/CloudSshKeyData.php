<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

use DateTimeImmutable;

final readonly class CloudSshKeyData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public ?string $fingerprint,
        public ?string $publicKey,
        public ?DateTimeImmutable $createdAt,
    ) {}
}
