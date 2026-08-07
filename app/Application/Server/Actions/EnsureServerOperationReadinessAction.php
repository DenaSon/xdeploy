<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Infrastructure\SSH\Enums\SSHCommandReadinessStatus;
use App\Infrastructure\SSH\Exceptions\SSHCommandUnavailableException;
use App\Infrastructure\SSH\Exceptions\SSHPasswordChangeRequiredException;
use App\Infrastructure\SSH\Services\SSHCommandReadinessInspector;

final readonly class EnsureServerOperationReadinessAction
{
    public function __construct(
        private SSHCommandReadinessInspector $commandReadiness,
        private EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
    ) {}

    /**
     * Validate the runtime prerequisites required before xDeploy executes
     * distro-dependent server operations.
     *
     * The SSH session must already be connected by the calling workflow.
     */
    public function handle(): OperatingSystemInfo
    {
        $status = $this->commandReadiness
            ->inspect();

        if (
            $status
            === SSHCommandReadinessStatus::PasswordChangeRequired
        ) {
            throw new SSHPasswordChangeRequiredException;
        }

        if (
            $status
            !== SSHCommandReadinessStatus::Ready
        ) {
            throw new SSHCommandUnavailableException;
        }

        return $this->ensureSupportedOperatingSystem
            ->handle();
    }
}
