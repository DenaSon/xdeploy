<?php

declare(strict_types=1);

namespace App\Domain\Server\Parsers;

use App\Domain\Server\DTOs\DiskInfoData;
use App\Domain\Server\Parsers\Contracts\Parser;
use RuntimeException;

final readonly class DiskParser implements Parser
{
    public function parse(string $output): DiskInfoData
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output));

        if (count($lines) < 2) {
            throw new RuntimeException('Invalid disk information.');
        }

        $disk = preg_split('/\s+/', trim($lines[1]));

        return new DiskInfoData(
            total: (int) $disk[0],
            used: (int) $disk[1],
            available: (int) $disk[2],
            usagePercent: (int) rtrim($disk[3], '%'),
        );
    }
}
