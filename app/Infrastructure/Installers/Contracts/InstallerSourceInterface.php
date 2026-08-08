<?php

declare(strict_types=1);

namespace App\Infrastructure\Installers\Contracts;

interface InstallerSourceInterface
{
    /**
     * Build a privileged shell command that stages, verifies and executes
     * an xDeploy-owned installer on the target server.
     */
    public function buildExecutionCommand(
        string $relativePath,
        string $expectedSha256,
    ): string;
}
