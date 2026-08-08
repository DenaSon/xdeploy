<?php

declare(strict_types=1);

namespace App\Domain\Server\Services;

use App\Domain\Server\DTOs\OperatingSystemInfo;

final readonly class SupportedOperatingSystemPolicy
{
    /**
     * @param  array<string, list<string>>  $matrix
     */
    public function __construct(
        private array $matrix,
    ) {}

    public function supports(
        OperatingSystemInfo $operatingSystem,
    ): bool {
        return $this->supportsIdVersion(
            id: $operatingSystem->id,
            versionId: $operatingSystem->versionId,
        );
    }

    public function supportsIdVersion(
        string $id,
        ?string $versionId,
    ): bool {
        $id = strtolower(
            trim($id),
        );

        $versionId = trim(
            (string) $versionId,
        );

        if (
            $id === ''
            || $versionId === ''
        ) {
            return false;
        }

        return in_array(
            $versionId,
            $this->matrix[$id] ?? [],
            true,
        );
    }

    /**
     * @return list<string>
     */
    public function supportedIds(): array
    {
        return array_keys(
            $this->matrix,
        );
    }

    /**
     * @return list<string>
     */
    public function supportedVersions(
        string $id,
    ): array {
        $id = strtolower(
            trim($id),
        );

        return $this->matrix[$id] ?? [];
    }
}
