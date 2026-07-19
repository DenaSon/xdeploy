<?php

declare(strict_types=1);

namespace App\Domain\Server\Parsers;

use App\Domain\Server\DTOs\CpuInfoData;
use App\Domain\Server\Parsers\Contracts\Parser;
use RuntimeException;

final readonly class CpuParser implements Parser
{
    public function parse(string $output): CpuInfoData
    {
        $data = [];

        foreach (preg_split('/\r\n|\r|\n/', trim($output)) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map(
                'trim',
                explode(':', $line, 2)
            );

            $data[$key] = $value;
        }

        if (! isset(
            $data['Architecture'],
            $data['Model name'],
            $data['Core(s) per socket'],
            $data['Thread(s) per core'],
        )) {
            throw new RuntimeException('Invalid CPU information.');
        }

        return new CpuInfoData(
            architecture: $data['Architecture'],
            model: $data['Model name'],
            cores: (int) $data['Core(s) per socket'],
            threads: (int) $data['Thread(s) per core'],
        );
    }
}
