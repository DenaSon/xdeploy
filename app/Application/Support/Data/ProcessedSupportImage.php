<?php

declare(strict_types=1);

namespace App\Application\Support\Data;

final readonly class ProcessedSupportImage
{
    public function __construct(
        public string $bytes,
        public string $mimeType,
        public int $width,
        public int $height,
    ) {}
}
