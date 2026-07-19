<?php
declare(strict_types=1);

namespace App\Infrastructure\SSH\DTOs;

final readonly class SSHResult
{
    public function __construct(
        public string $output,
        public int    $exitCode,
    )
    {
    }

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
