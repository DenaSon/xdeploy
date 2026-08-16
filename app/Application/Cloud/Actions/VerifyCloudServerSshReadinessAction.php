<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Application\Server\Actions\EnsureSupportedOperatingSystemAction;
use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Cloud\Exceptions\CloudServerProvisioningException;
use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Domain\Server\Enums\AuthenticationType;
use App\Domain\Server\Enums\PrivilegedExecutionMode;
use App\Domain\Server\Enums\ServerStatus;
use App\Domain\Server\Exceptions\RootPrivilegesRequiredException;
use App\Domain\Server\Exceptions\UnsupportedOperatingSystemException;
use App\Domain\Server\Services\PrivilegedExecutionPreflight;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Infrastructure\SSH\Contracts\SSHPortReadinessProbeInterface;
use App\Infrastructure\SSH\Enums\SSHCommandReadinessStatus;
use App\Infrastructure\SSH\Exceptions\SSHConnectionException;
use App\Infrastructure\SSH\Exceptions\SSHPasswordRotationException;
use App\Infrastructure\SSH\Services\SSHCommandReadinessInspector;
use App\Infrastructure\SSH\Services\SSHPasswordRotationService;
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
        private SSHPortReadinessProbeInterface $portReadinessProbe,
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

            /*
             * A previous process may have changed the remote password and
             * crashed before promoting the locally persisted candidate. Reconcile
             * that distributed state before attempting the normal SSH session.
             */
            $this->recoverPendingCredentialIfNeeded(
                $server,
            );

            $this->connect(
                $server,
            );

            $this->ensureCommandExecutionReady(
                $server,
            );

            $this->ensureSupportedOperatingSystem
                ->handle();

            $mode = $this->preflight->detect();

            $this->rotateProviderBootstrapCredentialIfNeeded(
                $server,
            );
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
            |CloudServerSshUnavailableException $exception
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
        $this->rotateCredential(
            server: $server,
            forcedPasswordChange: true,
        );
    }

    private function rotateProviderBootstrapCredentialIfNeeded(
        Server $server,
    ): void {
        if (! $this->usesProviderBootstrapCredential($server)) {
            return;
        }

        $this->rotateCredential(
            server: $server,
            forcedPasswordChange: false,
        );

        $this->assertCommandReadyAfterPasswordRotation();
    }

    private function usesProviderBootstrapCredential(
        Server $server,
    ): bool {
        return $this->isLiaraPasswordServer($server)
            && $server->bootstrap_credential_rotated_at === null;
    }

    private function isLiaraPasswordServer(Server $server): bool
    {
        return strtolower(
            trim((string) $server->cloud_provider),
        ) === CloudProviderType::Liara->value
            && $server->authentication_type === AuthenticationType::Password;
    }

    private function rotateCredential(
        Server $server,
        bool $forcedPasswordChange,
    ): void {
        $currentPassword = $server->credential;

        if (
            ! is_string($currentPassword)
            || trim($currentPassword) === ''
        ) {
            throw new CloudServerSshUnavailableException(
                'Cloud server password rotation requires the current credential.',
            );
        }

        $newPassword = Str::password(
            length: 32,
            letters: true,
            numbers: true,
            symbols: true,
            spaces: false,
        );

        /*
         * Persist first, mutate the remote host second. If the process dies
         * after passwd succeeds, the candidate secret survives for the next
         * readiness attempt and can be verified/promoted safely.
         */
        $this->persistPendingCredential(
            server: $server,
            pendingPassword: $newPassword,
        );

        $this->ssh->disconnect();

        if ($forcedPasswordChange) {
            $this->passwordRotation->rotate(
                server: $server,
                currentPassword: $currentPassword,
                newPassword: $newPassword,
            );
        } else {
            $this->passwordRotation->rotateManagedPassword(
                server: $server,
                currentPassword: $currentPassword,
                newPassword: $newPassword,
            );
        }

        $this->promotePendingCredential(
            $server,
        );

        $this->connect(
            $server,
        );
    }

    private function recoverPendingCredentialIfNeeded(Server $server): void
    {
        if (! $server->hasPendingCredential()) {
            return;
        }

        $pendingPassword = $server->pending_credential;

        if (! is_string($pendingPassword) || $pendingPassword === '') {
            return;
        }

        try {
            $this->passwordRotation->verifyCredential(
                server: $server,
                password: $pendingPassword,
            );

            $this->promotePendingCredential(
                $server,
            );

            return;
        } catch (SSHPasswordRotationException $pendingException) {
            $currentPassword = $server->credential;

            if (is_string($currentPassword) && $currentPassword !== '') {
                try {
                    $this->passwordRotation->verifyCredential(
                        server: $server,
                        password: $currentPassword,
                    );

                    $this->clearPendingCredential(
                        $server,
                    );

                    return;
                } catch (SSHPasswordRotationException) {
                    // Neither known credential could be verified. Preserve both.
                }
            }

            throw new CloudServerSshUnavailableException(
                message: 'Cloud server password rotation state is ambiguous; the recoverable pending credential was preserved.',
                previous: $pendingException,
            );
        }
    }

    private function persistPendingCredential(
        Server $server,
        string $pendingPassword,
    ): void {
        try {
            $server->forceFill([
                'pending_credential' => $pendingPassword,
            ]);

            $server->saveOrFail();
            $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server [%s] password rotation candidate could not be persisted before the remote mutation.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }

    private function promotePendingCredential(Server $server): void
    {
        $pendingPassword = $server->pending_credential;

        if (! is_string($pendingPassword) || $pendingPassword === '') {
            throw new CloudServerProvisioningException(
                sprintf(
                    'Cloud server [%s] has no pending credential to promote.',
                    $server->cloud_server_id,
                ),
            );
        }

        try {
            $attributes = [
                'credential' => $pendingPassword,
                'pending_credential' => null,
            ];

            if ($this->isLiaraPasswordServer($server)) {
                $attributes['bootstrap_credential_rotated_at'] =
                    $server->bootstrap_credential_rotated_at ?? now();
            }

            $server->forceFill($attributes);
            $server->saveOrFail();
            $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server [%s] password changed remotely but its recoverable pending credential could not be promoted.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }

    private function clearPendingCredential(Server $server): void
    {
        try {
            $server->forceFill([
                'pending_credential' => null,
            ]);

            $server->saveOrFail();
            $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerProvisioningException(
                message: sprintf(
                    'Cloud server [%s] stale password rotation candidate could not be cleared.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
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
