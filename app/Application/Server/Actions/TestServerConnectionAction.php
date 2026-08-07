<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Application\Server\Data\TestServerConnectionData;
use App\Application\Server\Data\TestServerConnectionResult;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Enums\SSHCommandReadinessStatus;
use App\Infrastructure\SSH\Services\SSHCommandReadinessInspector;
use App\Models\Server;

final readonly class TestServerConnectionAction
{
    public function __construct(
        private SSHConnectionInterface $connection,
        private SSHCommandReadinessInspector $commandReadiness,
        private EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
    ) {}

    public function execute(
        TestServerConnectionData $data,
    ): TestServerConnectionResult {
        $server = new Server([
            'host' => $data->host,
            'port' => $data->port,
            'username' => $data->username,
            'credential' => $data->credential,
            'authentication_type' => AuthenticationType::Password,
        ]);

        try {
            if (! $this->connection->connect($server)) {
                return TestServerConnectionResult::connectionFailed();
            }

            $commandStatus = $this->commandReadiness
                ->inspect();

            if (
                $commandStatus
                === SSHCommandReadinessStatus::PasswordChangeRequired
            ) {
                return TestServerConnectionResult::passwordChangeRequired();
            }

            if (
                $commandStatus
                === SSHCommandReadinessStatus::CommandUnavailable
            ) {
                return TestServerConnectionResult::commandUnavailable();
            }

            try {
                $operatingSystem = $this
                    ->ensureSupportedOperatingSystem
                    ->handle();
            } catch (
                UnsupportedOperatingSystemException $exception
            ) {
                return TestServerConnectionResult::unsupportedOperatingSystem(
                    $exception->operatingSystem,
                );
            }

            return TestServerConnectionResult::ready(
                $operatingSystem,
            );
        } finally {
            $this->connection->disconnect();
        }
    }
}
