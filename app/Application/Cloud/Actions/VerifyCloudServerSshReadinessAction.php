<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Application\Server\Actions\EnsureSupportedOperatingSystemAction;
use App\Domain\Cloud\Exceptions\CloudServerProvisioningException;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Domain\Server\Enums\PrivilegedExecutionMode;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Enums\SSHCommandReadinessStatus;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Infrastructure\SSH\Services\SSHCommandReadinessInspector;
use App\Infrastructure\SSH\Services\SSHPasswordRotationService;
use App\Infrastructure\SSH\Services\SSHPortReadinessProbe;
use App\Models\Server;
use Illuminate\Support\Str;
use Throwable;

final readonly class VerifyCloudServerSshReadinessAction
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedExecutionPreflight $preflight,
        private SSHCommandReadinessInspector $commandReadiness,
        private SSHPasswordRotationService $passwordRotation,
        private SSHPortReadinessProbe $portReadinessProbe,
        private EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
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
            $this->waitForSshPort(
                $server,
            );

            $this->connect(
                $server,
            );

            $this->ensureCommandExecutionReady(
                $server,
            );

            /*
             * OS compatibility is checked only after command execution
             * is confirmed. This guarantees /etc/os-release can be read
             * without confusing password/bootstrap failures with OS support.
             */
            $this->ensureSupportedOperatingSystem
                ->handle();

            $mode = $this->preflight->detect();
        } catch (
            RootPrivilegesRequiredException $exception
        ) {
            throw new CloudServerSshUnavailableException(
                message: 'Cloud server does not provide root or passwordless sudo access.',
                previous: $exception,
            );
        } catch (
            UnsupportedOperatingSystemException $exception
        ) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server operating system [%s] is not supported by xDeploy.',
                    $exception->operatingSystem->displayName(),
                ),
                previous: $exception,
            );
        } catch (
            CloudServerProvisioningException
            | CloudServerSshUnavailableException $exception
        ) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new CloudServerSshUnavailableException(
                message: 'Cloud server SSH readiness check failed.',
                previous: $exception,
            );
        } finally {
            $this->ssh->disconnect();
        }

        $this->activateServer(
            $server,
        );

        return $mode;
    }

    private function ensureCommandExecutionReady(
        Server $server,
    ): void {
        $status = $this->commandReadiness
            ->inspect();

        if ($status->isReady()) {
            return;
        }

        if ($status->requiresPasswordChange()) {
            $this->rotateExpiredPassword(
                $server,
            );

            $this->assertCommandReadyAfterPasswordRotation();

            return;
        }

        throw new CloudServerSshUnavailableException(
            'Cloud server SSH session was established but command execution is unavailable.',
        );
    }

    private function rotateExpiredPassword(
        Server $server,
    ): void {
        $currentPassword = $server->credential;

        if (
            ! is_string($currentPassword)
            || trim($currentPassword) === ''
        ) {
            throw new CloudServerSshUnavailableException(
                'Cloud server requires a password change but no current password is available.',
            );
        }

        $newPassword = Str::password(
            length: 32,
            letters: true,
            numbers: true,
            symbols: true,
            spaces: false,
        );

        $this->ssh->disconnect();

        $this->passwordRotation->rotate(
            server: $server,
            currentPassword: $currentPassword,
            newPassword: $newPassword,
        );

        $this->persistRotatedCredential(
            server: $server,
            newPassword: $newPassword,
        );

        $this->connect(
            $server,
        );
    }

    private function assertCommandReadyAfterPasswordRotation(): void
    {
        $status = $this->commandReadiness
            ->inspect();

        if ($status === SSHCommandReadinessStatus::Ready) {
            return;
        }

        throw new CloudServerSshUnavailableException(
            'Cloud server password was changed but command execution is still unavailable.',
        );
    }

    private function persistRotatedCredential(
        Server $server,
        string $newPassword,
    ): void {
        try {
            $server->forceFill([
                'credential' => $newPassword,
            ]);

            $server->saveOrFail();

            $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server [%s] password was rotated but the new credential could not be persisted.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }

    private function waitForSshPort(
        Server $server,
    ): void {
        if (
            $this->portReadinessProbe
                ->waitUntilReady(
                    $server,
                )
        ) {
            return;
        }

        throw new CloudServerSshUnavailableException(
            'Cloud server became active but SSH port did not become available.',
        );
    }

    private function connect(
        Server $server,
    ): void {
        if (
            $this->ssh->connect(
                $server,
            )
        ) {
            return;
        }

        throw new SSHConnectionException(
            'Unable to establish SSH connection.',
        );
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
                    'Cloud server [%s] passed readiness checks but could not be activated.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }
}
