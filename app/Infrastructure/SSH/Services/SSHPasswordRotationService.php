<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Infrastructure\SSH\Contracts\SSHCredentialVerifierInterface;
use App\Infrastructure\SSH\Exceptions\SSHPasswordRotationException;
use App\Infrastructure\SSH\Security\SSHConnectionTargetPolicy;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use phpseclib3\Net\SSH2;
use SensitiveParameter;
use Throwable;

final readonly class SSHPasswordRotationService implements SSHCredentialVerifierInterface
{
    private const string VERIFICATION_MARKER =
        '__xdeploy_password_rotation_ready__';

    public function __construct(
        private SSHConnectionTargetPolicy $targetPolicy,
    ) {}

    public function rotate(
        Server $server,
        #[SensitiveParameter]
        string $currentPassword,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        $this->assertPasswords(
            currentPassword: $currentPassword,
            newPassword: $newPassword,
        );

        $host = $this->targetPolicy->resolve(
            $server->host,
        );

        $ssh = $this->connect(
            host: $host,
            server: $server,
            password: $currentPassword,
        );

        try {
            $this->completeForcedPasswordChange(
                ssh: $ssh,
                currentPassword: $currentPassword,
                newPassword: $newPassword,
            );
        } catch (SSHPasswordRotationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SSHPasswordRotationException(
                message: 'SSH password rotation failed.',
                previous: $exception,
            );
        } finally {
            $this->disconnect(
                $ssh,
            );
        }

        $this->verifyCredential(
            server: $server,
            password: $newPassword,
        );
    }

    public function rotateManagedPassword(
        Server $server,
        #[SensitiveParameter]
        string $currentPassword,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        $this->assertPasswords(
            currentPassword: $currentPassword,
            newPassword: $newPassword,
        );

        if (strtolower(trim($server->username)) !== 'root') {
            throw new SSHPasswordRotationException(
                'Managed SSH password rotation currently requires root SSH access.',
            );
        }

        $host = $this->targetPolicy->resolve(
            $server->host,
        );

        $ssh = $this->connect(
            host: $host,
            server: $server,
            password: $currentPassword,
        );

        try {
            $this->completeManagedPasswordChange(
                ssh: $ssh,
                newPassword: $newPassword,
            );
        } catch (SSHPasswordRotationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SSHPasswordRotationException(
                message: 'Managed SSH password rotation failed.',
                previous: $exception,
            );
        } finally {
            $this->disconnect(
                $ssh,
            );
        }

        $this->verifyCredential(
            server: $server,
            password: $newPassword,
        );
    }

    public function verifyCredential(
        Server $server,
        #[SensitiveParameter]
        string $password,
    ): void {
        if ($password === '') {
            throw new SSHPasswordRotationException(
                'SSH credential verification requires a password.',
            );
        }

        $host = $this->targetPolicy->resolve(
            $server->host,
        );

        $this->verifyPassword(
            host: $host,
            server: $server,
            password: $password,
        );
    }

    private function assertPasswords(
        #[SensitiveParameter]
        string $currentPassword,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        if ($currentPassword === '') {
            throw new SSHPasswordRotationException(
                'Current SSH password is missing.',
            );
        }

        if ($newPassword === '') {
            throw new SSHPasswordRotationException(
                'New SSH password is missing.',
            );
        }

        if (
            hash_equals(
                $currentPassword,
                $newPassword,
            )
        ) {
            throw new SSHPasswordRotationException(
                'New SSH password must differ from the current password.',
            );
        }
    }

    private function completeForcedPasswordChange(
        SSH2 $ssh,
        #[SensitiveParameter]
        string $currentPassword,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        $ssh->setTimeout(
            SSHTimeout::NORMAL,
        );

        /*
         * Opening the interactive shell triggers PAM's forced
         * password-change flow on the affected cloud images.
         */

        $this->waitForPasswordPrompt(
            $ssh,
        );

        $ssh->write(
            $currentPassword."\n",
        );

        $this->waitForPasswordPrompt(
            $ssh,
        );

        $ssh->write(
            $newPassword."\n",
        );

        $this->waitForPasswordPrompt(
            $ssh,
        );

        $ssh->write(
            $newPassword."\n",
        );

        $this->assertPasswordUpdateConfirmed(
            $ssh,
        );
    }

    private function completeManagedPasswordChange(
        SSH2 $ssh,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        $ssh->setTimeout(
            SSHTimeout::NORMAL,
        );

        /*
         * Liara supplies a usable root bootstrap password instead of a
         * password-expired account. Rotate it through an interactive
         * `passwd` session so the new secret never appears in a command
         * string or structured SSH logs.
         */
        $ssh->write(
            "passwd\n",
        );

        $this->waitForPrompt(
            ssh: $ssh,
            pattern: '/New password:\s*/i',
            errorMessage: 'Expected new-password prompt was not received.',
        );

        $ssh->write(
            $newPassword."\n",
        );

        $this->waitForPrompt(
            ssh: $ssh,
            pattern: '/Retype new password:\s*/i',
            errorMessage: 'Expected password confirmation prompt was not received.',
        );

        $ssh->write(
            $newPassword."\n",
        );

        $this->assertPasswordUpdateConfirmed(
            $ssh,
        );
    }

    private function waitForPasswordPrompt(
        SSH2 $ssh,
    ): void {
        $this->waitForPrompt(
            ssh: $ssh,
            pattern: '/password:\s*/i',
            errorMessage: 'Expected password-change prompt was not received.',
        );
    }

    private function waitForPrompt(
        SSH2 $ssh,
        string $pattern,
        string $errorMessage,
    ): void {
        $output = $ssh->read(
            $pattern,
            SSH2::READ_REGEX,
        );

        if (
            $ssh->isTimeout()
            || ! is_string($output)
        ) {
            throw new SSHPasswordRotationException(
                $errorMessage,
            );
        }
    }

    private function assertPasswordUpdateConfirmed(
        SSH2 $ssh,
    ): void {
        $output = $ssh->read(
            '/password updated successfully/i',
            SSH2::READ_REGEX,
        );

        if (
            $ssh->isTimeout()
            || ! is_string($output)
            || preg_match(
                '/password updated successfully/i',
                $output,
            ) !== 1
        ) {
            throw new SSHPasswordRotationException(
                'Remote server did not confirm the password change.',
            );
        }
    }

    private function verifyPassword(
        string $host,
        Server $server,
        #[SensitiveParameter]
        string $password,
    ): void {
        $ssh = $this->connect(
            host: $host,
            server: $server,
            password: $password,
        );

        try {
            $ssh->setTimeout(
                SSHTimeout::QUICK,
            );

            $output = $ssh->exec(
                sprintf(
                    "printf '%s'",
                    self::VERIFICATION_MARKER,
                ),
            );

            if (
                ! is_string($output)
                || trim($output)
                !== self::VERIFICATION_MARKER
            ) {
                throw new SSHPasswordRotationException(
                    'SSH password could not execute commands.',
                );
            }
        } finally {
            $this->disconnect(
                $ssh,
            );
        }
    }

    private function connect(
        string $host,
        Server $server,
        #[SensitiveParameter]
        string $password,
    ): SSH2 {
        $ssh = new SSH2(
            host: $host,
            port: $server->port,
            timeout: SSHTimeout::CONNECTION,
        );

        $ssh->setTimeout(
            SSHTimeout::AUTHENTICATION,
        );

        if (
            ! $ssh->login(
                $server->username,
                $password,
            )
        ) {
            throw new SSHPasswordRotationException(
                'SSH authentication failed during password rotation.',
            );
        }

        return $ssh;
    }

    private function disconnect(
        SSH2 $ssh,
    ): void {
        try {
            $ssh->disconnect();
        } catch (Throwable) {
            //
        }
    }
}
