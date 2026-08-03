<?php

declare(strict_types=1);

namespace App\Domain\Cloud\DTOs;

final readonly class CloudSecurityGroupData
{
    public function __construct(
        public string $id,
        public string $name,
        public string $regionId,
        public ?string $description,
        public bool $isDefault,
        public bool $isReadOnly,
    ) {}
}
