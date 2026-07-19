<?php

declare(strict_types=1);

namespace App\Domain\Server\Parsers;

use App\Domain\Server\DTOs\LoadAverageData;
use App\Domain\Server\Parsers\Contracts\Parser;
use RuntimeException;

final readonly class LoadAverageParser implements Parser
{
    public function parse(string $output): LoadAverageData
    {
        $parts = preg_split('/\s+/', trim($output));

        if (count($parts) < 3) {
            throw new RuntimeException('Invalid load average information.');
        }

        return new LoadAverageData(
            oneMinute: (float) $parts[0],
            fiveMinutes: (float) $parts[1],
            fifteenMinutes: (float) $parts[2],
        );
    }
}
