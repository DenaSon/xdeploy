<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudImageData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public string $distribution,
        public string $version,
        public ?string $architecture,
        public ?int $minDiskGiB,
        public ?int $minMemoryMiB,
        public bool $supportsSshKey,
        public bool $supportsPassword,
    ) {}
}
