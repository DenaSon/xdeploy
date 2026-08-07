<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Application\Server\Data\TestServerConnectionData;
use App\Application\Server\Data\TestServerConnectionResult;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Exceptions\SSHCommandUnavailableException;
use App\Infrastructure\SSH\Exceptions\SSHPasswordChangeRequiredException;
use App\Models\Server;

final readonly class TestServerConnectionAction
{
    public function __construct(
        private SSHConnectionInterface $connection,
        private EnsureServerOperationReadinessAction $serverReadiness,
        private PrivilegedExecutionPreflight $privilegedExecution,
    ) {}

    public function execute(
        TestServerConnectionData $data,
    ): TestServerConnectionResult {
        $server = $this->makeServer($data);

        try {
            if (! $this->connection->connect($server)) {
                return TestServerConnectionResult::connectionFailed();
            }

            return $this->inspectReadiness();
        } finally {
            $this->connection->disconnect();
        }
    }

    private function inspectReadiness(): TestServerConnectionResult
    {
        try {
            $operatingSystem = $this->serverReadiness
                ->handle();
        } catch (SSHPasswordChangeRequiredException) {
            return TestServerConnectionResult::passwordChangeRequired();
        } catch (SSHCommandUnavailableException) {
            return TestServerConnectionResult::commandUnavailable();
        } catch (UnsupportedOperatingSystemException $exception) {
            return TestServerConnectionResult::unsupportedOperatingSystem(
                $exception->operatingSystem,
            );
        }

        try {
            $this->privilegedExecution
                ->detect();
        } catch (RootPrivilegesRequiredException) {
            return TestServerConnectionResult::insufficientPrivileges(
                $operatingSystem,
            );
        }

        return TestServerConnectionResult::ready(
            $operatingSystem,
        );
    }

    private function makeServer(
        TestServerConnectionData $data,
    ): Server {
        return new Server([
            'host' => $data->host,
            'port' => $data->port,
            'username' => $data->username,
            'credential' => $data->credential,
            'authentication_type' => AuthenticationType::Password,
        ]);
    }
}
