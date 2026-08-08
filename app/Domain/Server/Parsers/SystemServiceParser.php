<?php

declare(strict_types=1);

namespace App\Domain\Server\Parsers;

use App\Domain\Server\DTOs\SystemServiceData;
use App\Domain\Server\Parsers\Contracts\Parser;

final readonly class SystemServiceParser implements Parser
{
    /**
     * @return list<SystemServiceData>
     */
    public function parse(string $output): array
    {
        $output = $this->stripAnsiSequences(
            $output,
        );

        $services = [];

        $lines = preg_split(
            '/\R/',
            $output,
        );

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $service = $this->parseLine(
                $line,
            );

            if ($service === null) {
                continue;
            }

            $services[$service->unit] = $service;
        }

        ksort(
            $services,
            SORT_NATURAL | SORT_FLAG_CASE,
        );

        return array_values(
            $services,
        );
    }

    private function parseLine(
        string $line,
    ): ?SystemServiceData {
        $line = trim(
            $line,
        );

        if ($line === '') {
            return null;
        }

        $line = preg_replace(
            '/^[●*]\s+/u',
            '',
            $line,
        ) ?? $line;

        $columns = preg_split(
            '/\s+/',
            $line,
            5,
        );

        if (
            $columns === false
            || count($columns) < 4
        ) {
            return null;
        }

        $unit = trim(
            $columns[0],
        );

        if (
            $unit === ''
            || ! str_ends_with(
                $unit,
                '.service',
            )
        ) {
            return null;
        }

        $loadState = strtolower(
            trim(
                $columns[1],
            ),
        );

        $activeState = strtolower(
            trim(
                $columns[2],
            ),
        );

        $subState = strtolower(
            trim(
                $columns[3],
            ),
        );

        if (
            $loadState === ''
            || $activeState === ''
            || $subState === ''
        ) {
            return null;
        }

        $name = substr(
            $unit,
            0,
            -strlen('.service'),
        );

        if ($name === '') {
            return null;
        }

        $description = trim(
            $columns[4] ?? '',
        );

        return new SystemServiceData(
            unit: $unit,
            name: $name,
            loadState: $loadState,
            activeState: $activeState,
            subState: $subState,
            description: $description !== ''
                ? $description
                : $name,
        );
    }

    private function stripAnsiSequences(
        string $output,
    ): string {
        return preg_replace(
            '/\x1B(?:[@-Z\\-_]|\[[0-?]*[ -\/]*[@-~])/',
            '',
            $output,
        ) ?? $output;
    }
}
