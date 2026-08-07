<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Enums\SSHCommandReadinessStatus;
use App\Infrastructure\SSH\Security\SSHPasswordExpirationDetector;

final readonly class SSHCommandReadinessInspector
{
    private const string PROBE_MARKER =
        '__xdeploy_ssh_ready__';

    private const string PROBE_COMMAND =
        "printf '__xdeploy_ssh_ready__'";

    private const int PROBE_TIMEOUT_SECONDS = 20;

    public function __construct(
        private SSHConnectionInterface $ssh,
        private SSHPasswordExpirationDetector $passwordExpirationDetector,
    ) {}

    public function inspect(): SSHCommandReadinessStatus
    {
        $result = $this->ssh->executeWithResult(
            self::PROBE_COMMAND,
            self::PROBE_TIMEOUT_SECONDS,
        );

        $output = trim(
            $result->output,
        );

        if (
            $result->successful()
            && hash_equals(
                self::PROBE_MARKER,
                $output,
            )
        ) {
            return SSHCommandReadinessStatus::Ready;
        }

        if (
            $this->passwordExpirationDetector
                ->detects(
                    $result->output,
                )
        ) {
            return SSHCommandReadinessStatus::PasswordChangeRequired;
        }

        return SSHCommandReadinessStatus::CommandUnavailable;
    }
}
