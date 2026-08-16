<?php

declare(strict_types=1);

namespace App\Application\Cloud\Services;

use App\Domain\Cloud\Exceptions\CloudServerSshUnavailableException;
use App\Infrastructure\SSH\Contracts\SSHCredentialVerifierInterface;
use App\Infrastructure\SSH\Exceptions\SSHPasswordRotationException;
use App\Models\Server;
use SensitiveParameter;
use Throwable;

final readonly class CloudServerCredentialRecovery
{
    public function __construct(
        private SSHCredentialVerifierInterface $verifier,
    ) {}

    public function recoverPendingCredentialIfNeeded(
        Server $server,
        bool $markBootstrapCredentialRotated,
    ): void {
        if (! $server->hasPendingCredential()) {
            return;
        }

        $pendingPassword = $server->pending_credential;

        if (! is_string($pendingPassword) || $pendingPassword === '') {
            return;
        }

        try {
            $this->verifier->verifyCredential(
                server: $server,
                password: $pendingPassword,
            );

            $this->promotePendingCredential(
                server: $server,
                markBootstrapCredentialRotated: $markBootstrapCredentialRotated,
            );

            return;
        } catch (SSHPasswordRotationException $pendingException) {
            $currentPassword = $server->credential;

            if (is_string($currentPassword) && $currentPassword !== '') {
                try {
                    $this->verifier->verifyCredential(
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

    public function persistPendingCredential(
        Server $server,
        #[SensitiveParameter]
        string $pendingPassword,
    ): void {
        if ($pendingPassword === '') {
            throw new CloudServerSshUnavailableException(
                'Cloud server password rotation candidate cannot be empty.',
            );
        }

        try {
            $server->forceFill([
                'pending_credential' => $pendingPassword,
            ]);

            $server->saveOrFail();
            $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerSshUnavailableException(
                message: sprintf(
                    'Cloud server [%s] password rotation candidate could not be persisted before the remote mutation.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }

    public function promotePendingCredential(
        Server $server,
        bool $markBootstrapCredentialRotated,
    ): void {
        $pendingPassword = $server->pending_credential;

        if (! is_string($pendingPassword) || $pendingPassword === '') {
            throw new CloudServerSshUnavailableException(
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

            if ($markBootstrapCredentialRotated) {
                $attributes['bootstrap_credential_rotated_at'] =
                    $server->bootstrap_credential_rotated_at ?? now();
            }

            $server->forceFill($attributes);
            $server->saveOrFail();
            $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerSshUnavailableException(
                message: sprintf(
                    'Cloud server [%s] password changed remotely but its recoverable pending credential could not be promoted.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }

    public function clearPendingCredential(Server $server): void
    {
        try {
            $server->forceFill([
                'pending_credential' => null,
            ]);

            $server->saveOrFail();
            $server->refresh();
        } catch (Throwable $exception) {
            throw new CloudServerSshUnavailableException(
                message: sprintf(
                    'Cloud server [%s] stale password rotation candidate could not be cleared.',
                    $server->cloud_server_id,
                ),
                previous: $exception,
            );
        }
    }
}
