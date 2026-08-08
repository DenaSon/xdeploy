<?php

declare(strict_types=1);

namespace App\Domain\Server\Parsers;

use App\Domain\Server\DTOs\DockerContainerData;
use App\Domain\Server\Parsers\Contracts\Parser;

final readonly class DockerContainerParser implements Parser
{
    /**
     * Docker discovery returns one JSON object per line.
     *
     * @return list<DockerContainerData>
     */
    public function parse(string $output): array
    {
        $containers = [];

        $lines = preg_split(
            '/\R/',
            trim(
                $output,
            ),
        );

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $line = trim(
                $line,
            );

            if ($line === '') {
                continue;
            }

            $data = json_decode(
                $line,
                true,
            );

            if (! is_array($data)) {
                continue;
            }

            $name = trim(
                (string) (
                    $data['Names']
                    ?? ''
                ),
            );

            if ($name === '') {
                continue;
            }

            $containers[$name] = new DockerContainerData(
                name: $name,
                image: trim(
                    (string) (
                        $data['Image']
                        ?? ''
                    ),
                ),
                state: strtolower(
                    trim(
                        (string) (
                            $data['State']
                            ?? 'unknown'
                        ),
                    ),
                ),
                status: trim(
                    (string) (
                        $data['Status']
                        ?? ''
                    ),
                ),
                ports: trim(
                    (string) (
                        $data['Ports']
                        ?? ''
                    ),
                ),
            );
        }

        ksort(
            $containers,
            SORT_NATURAL | SORT_FLAG_CASE,
        );

        return array_values(
            $containers,
        );
    }
}
