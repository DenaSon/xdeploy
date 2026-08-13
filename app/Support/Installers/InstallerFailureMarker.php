<?php

declare(strict_types=1);

namespace App\Support\Installers;

final readonly class InstallerFailureMarker
{
    private const string PATTERN =
        '/\[xDeploy\]\[([a-z0-9_-]+)\]\[error\] stage=([a-z0-9_]+) exit_code=([0-9]+)/';

    public function __construct(
        public string $component,
        public string $stage,
        public int $exitCode,
    ) {}

    public static function fromOutput(string $output): ?self
    {
        if (
            preg_match(
                self::PATTERN,
                $output,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        return new self(
            component: $matches[1],
            stage: $matches[2],
            exitCode: (int) $matches[3],
        );
    }

    public function failureCode(): string
    {
        return sprintf(
            '%s_%s',
            $this->component,
            $this->stage,
        );
    }
}
