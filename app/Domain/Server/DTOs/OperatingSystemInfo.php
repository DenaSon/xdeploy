<?php

declare(strict_types=1);

namespace App\Domain\Server\DTOs;

final readonly class OperatingSystemInfo
{
    /**
     * @param list<string> $idLike
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $versionId,
        public ?string $prettyName,
        public array $idLike = [],
    ) {}

    public function displayName(): string
    {
        if (
            is_string($this->prettyName)
            && trim($this->prettyName) !== ''
        ) {
            return trim($this->prettyName);
        }

        if (
            $this->versionId !== null
            && trim($this->versionId) !== ''
        ) {
            return sprintf(
                '%s %s',
                $this->name,
                $this->versionId,
            );
        }

        return $this->name;
    }
}
