<?php

declare(strict_types=1);

namespace App\Infrastructure\Linux\Services;

use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Infrastructure\Linux\Exceptions\OperatingSystemInspectionException;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;

final readonly class OperatingSystemInspector
{
    private const int INSPECTION_TIMEOUT_SECONDS = 20;

    private const string OS_RELEASE_COMMAND =
        'if [ -r /etc/os-release ]; then cat /etc/os-release; '
        . 'elif [ -r /usr/lib/os-release ]; then cat /usr/lib/os-release; '
        . 'else exit 1; fi';

    public function __construct(
        private SSHConnectionInterface $ssh,
    ) {}

    public function inspect(): OperatingSystemInfo
    {
        $result = $this->ssh->executeWithResult(
            self::OS_RELEASE_COMMAND,
            self::INSPECTION_TIMEOUT_SECONDS,
        );

        if (! $result->successful()) {
            throw new OperatingSystemInspectionException(
                'Unable to read operating system information from the server.',
            );
        }

        $values = $this->parseOsRelease(
            $result->output,
        );

        $id = $this->normalizeId(
            $values['ID'] ?? '',
        );

        if ($id === '') {
            throw new OperatingSystemInspectionException(
                'Operating system information does not contain a valid ID.',
            );
        }

        $name = trim(
            $values['NAME'] ?? $id,
        );

        if ($name === '') {
            $name = $id;
        }

        $versionId = $this->nullableValue(
            $values['VERSION_ID'] ?? null,
        );

        $prettyName = $this->nullableValue(
            $values['PRETTY_NAME'] ?? null,
        );

        return new OperatingSystemInfo(
            id: $id,
            name: $name,
            versionId: $versionId,
            prettyName: $prettyName,
            idLike: $this->parseIdLike(
                $values['ID_LIKE'] ?? '',
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseOsRelease(
        string $output,
    ): array {
        $values = [];

        $lines = preg_split(
            '/\R/',
            $output,
        );

        if ($lines === false) {
            return $values;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if (
                $line === ''
                || str_starts_with($line, '#')
                || ! str_contains($line, '=')
            ) {
                continue;
            }

            [$key, $value] = explode(
                '=',
                $line,
                2,
            );

            $key = trim($key);

            if (
                $key === ''
                || preg_match('/^[A-Z0-9_]+$/', $key) !== 1
            ) {
                continue;
            }

            $values[$key] = $this->decodeValue(
                $value,
            );
        }

        return $values;
    }

    private function decodeValue(
        string $value,
    ): string {
        $value = trim($value);

        if (strlen($value) < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[
            strlen($value) - 1
        ];

        if (
            ($first === '"' && $last === '"')
            || ($first === "'" && $last === "'")
        ) {
            $value = substr(
                $value,
                1,
                -1,
            );
        }

        return $value;
    }

    private function normalizeId(
        string $id,
    ): string {
        return strtolower(
            trim($id),
        );
    }

    private function nullableValue(
        ?string $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    /**
     * @return list<string>
     */
    private function parseIdLike(
        string $idLike,
    ): array {
        $idLike = strtolower(
            trim($idLike),
        );

        if ($idLike === '') {
            return [];
        }

        $items = preg_split(
            '/\s+/',
            $idLike,
        );

        if ($items === false) {
            return [];
        }

        return array_values(
            array_filter(
                array_unique($items),
                static fn (string $item): bool =>
                    $item !== '',
            ),
        );
    }
}
