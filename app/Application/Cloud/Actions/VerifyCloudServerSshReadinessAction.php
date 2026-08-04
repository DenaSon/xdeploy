<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Domain\Cloud\Exceptions\CloudServerProvisioningException;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Domain\Server\Enums\PrivilegedExecutionMode;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Models\Server;
use Throwable;

final readonly class VerifyCloudServerSshReadinessAction
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedExecutionPreflight $preflight,
    ) {}

    public function handle(
        Server $server,
    ): PrivilegedExecutionMode {
        if (! $server->hasConnectionHost()) {
            throw new CloudServerSshUnavailableException(
                'Cloud server does not have a connection host.',
            );
        }

        try {
            if (! $this->ssh->connect($server)) {
                throw new SSHConnectionException(
                    'Unable to establish SSH connection.',
                );
            }

            $mode = $this->preflight->detect();
        } catch (
            RootPrivilegesRequiredException $exception
        ) {
            throw new CloudServerSshUnavailableException(
                message: 'Cloud server does not provide root or passwordless sudo access.',
                previous: $exception,
            );
        } catch (Throwable $exception) {
            throw new CloudServerSshUnavailableException(
                message: 'Cloud server SSH readiness check failed.',
                previous: $exception,
            );
        } finally {
            $this->ssh->disconnect();
        }

        $this->activateServer($server);

        return $mode;
    }

    private function activateServer(
        Server $server,
    ): void {
        try {
            $server->forceFill([
                'status' => ServerStatus::Active,
            ]);

            $server->saveOrFail();
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server [%s] passed SSH readiness but could not be activated.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }
}
