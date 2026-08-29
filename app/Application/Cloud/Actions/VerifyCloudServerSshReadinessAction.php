<?php

declare(strict_types=1);

namespace App\Application\Cloud\Actions;

use App\Application\Analytics\Contracts\ProductAnalytics;
use App\Application\Analytics\ProductAnalyticsEvent;
use App\Application\Cloud\Servers\CloudServerCapabilityResolver;
use App\Application\Cloud\Services\CloudServerCredentialRecovery;
use App\Application\Server\Actions\EnsureSupportedOperatingSystemAction;
use App\Domain\Cloud\Contracts\CloudServerBootstrapCredentialRotationInterface;
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
use App\Infrastructure\SSH\Services\SSHCommandReadinessInspector;
use App\Infrastructure\SSH\Services\SSHKeyBootstrapService;
use App\Infrastructure\SSH\Services\SSHPasswordRotationService;
use App\Models\Server;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final readonly class VerifyCloudServerSshReadinessAction
{
    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedExecutionPreflight $preflight,
        private SSHCommandReadinessInspector $commandReadiness,
        private SSHPasswordRotationService $passwordRotation,
        private SSHKeyBootstrapService $sshKeyBootstrap,
        private CloudServerCredentialRecovery $credentialRecovery,
        private SSHPortReadinessProbeInterface $portReadinessProbe,
        private EnsureSupportedOperatingSystemAction $ensureSupportedOperatingSystem,
        private CloudServerCapabilityResolver $capabilities,
        private ProductAnalytics $analytics,
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

            $this->recoverCredentialStateIfNeeded(
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

        $this->captureServerReady(
            $server,
        );

        return $mode;
    }

    private function recoverCredentialStateIfNeeded(
        Server $server,
    ): void {
        /*
         * A previous SSH-key bootstrap may have changed the remote password
         * and crashed before promoting the locally persisted candidate. Keep
         * the private key active locally until the pending password is
         * verified and the bootstrap key has been removed remotely.
         */
        if ($server->authentication_type === AuthenticationType::SSHKey) {
            if (
                $server->hasPendingCredential()
                && $this->providerUsesBootstrapCredential($server)
            ) {
                $this->completeSshKeyBootstrapCredentialRotation(
                    $server,
                );
            }

            return;
        }

        /*
         * A previous password rotation may have changed the remote password
         * and crashed before promoting the locally persisted candidate.
         */
        $this->credentialRecovery->recoverPendingCredentialIfNeeded(
            server: $server,
            markBootstrapCredentialRotated: $this->providerUsesBootstrapCredential(
                $server,
            ),
        );
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
        if (! $this->requiresProviderBootstrapCredentialRotation($server)) {
            return;
        }

        if ($server->authentication_type === AuthenticationType::SSHKey) {
            $this->rotateSshKeyBootstrapCredential(
                $server,
            );

            $this->assertCommandReadyAfterPasswordRotation();

            return;
        }

        if ($server->authentication_type !== AuthenticationType::Password) {
            throw new CloudServerSshUnavailableException(
                'Cloud server bootstrap credential rotation uses an unsupported authentication type.',
            );
        }

        $this->rotateCredential(
            server: $server,
            forcedPasswordChange: false,
        );

        $this->assertCommandReadyAfterPasswordRotation();
    }

    private function requiresProviderBootstrapCredentialRotation(
        Server $server,
    ): bool {
        return $this->providerUsesBootstrapCredential($server)
            && $server->bootstrap_credential_rotated_at === null;
    }

    private function providerUsesBootstrapCredential(
        Server $server,
    ): bool {
        return $this->capabilities->supports(
            server: $server,
            capability: CloudServerBootstrapCredentialRotationInterface::class,
        );
    }

    private function rotateSshKeyBootstrapCredential(
        Server $server,
    ): void {
        $newPassword = Str::password(
            length: 32,
            letters: true,
            numbers: true,
            symbols: true,
            spaces: false,
        );

        $this->credentialRecovery->persistPendingCredential(
            server: $server,
            pendingPassword: $newPassword,
        );

        $this->completeSshKeyBootstrapCredentialRotation(
            $server,
        );

        $this->connect(
            $server,
        );
    }

    private function completeSshKeyBootstrapCredentialRotation(
        Server $server,
    ): void {
        $pendingPassword = $server->pending_credential;

        if (! is_string($pendingPassword) || $pendingPassword === '') {
            throw new CloudServerSshUnavailableException(
                'SSH key bootstrap requires a recoverable pending password.',
            );
        }

        $this->ssh->disconnect();

        $this->sshKeyBootstrap->bootstrapToPassword(
            server: $server,
            newPassword: $pendingPassword,
        );

        $this->credentialRecovery->promotePendingCredential(
            server: $server,
            markBootstrapCredentialRotated: true,
            authenticationType: AuthenticationType::Password,
        );
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
        $this->credentialRecovery->persistPendingCredential(
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

        $this->credentialRecovery->promotePendingCredential(
            server: $server,
            markBootstrapCredentialRotated: $this->providerUsesBootstrapCredential(
                $server,
            ),
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

    private function captureServerReady(Server $server): void
    {
        if (! $server->isCloudProvisioned()) {
            return;
        }

        try {
            $this->analytics->capture(
                ProductAnalyticsEvent::ServerReady,
                $server->user_id,
                [
                    'server_id' => $server->getKey(),
                    'provider' => $server->cloud_provider?->value,
                    'region_id' => $server->cloud_region,
                ],
            );
        } catch (Throwable $exception) {
            Log::warning(
                'analytics.server_ready.capture_failed',
                [
                    'server_id' => $server->getKey(),
                    'exception_type' => $exception::class,
                ],
            );
        }
    }
}
