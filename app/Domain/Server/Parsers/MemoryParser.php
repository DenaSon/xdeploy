<?php

declare(strict_types=1);

namespace App\Domain\Server\Parsers;

use App\Domain\Server\DTOs\MemoryInfoData;
use App\Domain\Server\Parsers\Contracts\Parser;

final readonly class MemoryParser implements Parser
{
    public function parse(string $output): MemoryInfoData
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output));

        if (count($lines) < 2) {
            throw new \RuntimeException('Invalid memory information.');
        }

        $memory = preg_split('/\s+/', trim($lines[1]));

        return new MemoryInfoData(
            total: (int) $memory[1],
            used: (int) $memory[2],
            free: (int) $memory[3],
            available: (int) $memory[6],
            usagePercent: (int) round(($memory[2] / $memory[1]) * 100),
        );
    }
}
