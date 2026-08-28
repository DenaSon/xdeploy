<?php

declare(strict_types=1);

namespace App\Infrastructure\SSH\Services;

use App\Domain\Server\Enums\AuthenticationType;
use App\Infrastructure\SSH\Exceptions\SSHKeyBootstrapException;
use App\Infrastructure\SSH\Exceptions\SSHPasswordRotationException;
use App\Infrastructure\SSH\Security\SSHConnectionTargetPolicy;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use SensitiveParameter;
use Throwable;

final readonly class SSHKeyBootstrapService
{
    private const string PASSWORD_AUTH_READY_MARKER =
        '__coreflare_password_auth_ready__';

    private const string KEY_REMOVED_MARKER =
        '__coreflare_bootstrap_key_removed__';

    public function __construct(
        private SSHConnectionTargetPolicy $targetPolicy,
        private SSHPasswordRotationService $passwordRotation,
    ) {}

    public function bootstrapToPassword(
        Server $server,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        $this->assertBootstrapInput(
            server: $server,
            newPassword: $newPassword,
        );

        $privateKey = $server->credential;

        if (! is_string($privateKey) || trim($privateKey) === '') {
            throw new SSHKeyBootstrapException(
                'SSH key bootstrap requires a private key credential.',
            );
        }

        try {
            $key = PublicKeyLoader::loadPrivateKey(
                $privateKey,
            );

            $publicKeyBlob = $this->publicKeyBlob(
                $key->getPublicKey()->toString('OpenSSH'),
            );
        } catch (SSHKeyBootstrapException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new SSHKeyBootstrapException(
                message: 'SSH key bootstrap credential could not be loaded.',
                previous: $exception,
            );
        }

        /*
         * A prior attempt may have changed the remote password and crashed
         * before the local pending credential was promoted. Verification is
         * deliberately attempted first so retries remain idempotent.
         */
        if (! $this->passwordAlreadyWorks(
            server: $server,
            password: $newPassword,
        )) {
            $host = $this->targetPolicy->resolve(
                $server->host,
            );

            $ssh = $this->connectWithKey(
                host: $host,
                server: $server,
                privateKey: $privateKey,
            );

            try {
                $this->enablePasswordAuthentication(
                    $ssh,
                );

                $this->changeRootPassword(
                    ssh: $ssh,
                    newPassword: $newPassword,
                );
            } catch (SSHKeyBootstrapException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new SSHKeyBootstrapException(
                    message: 'SSH key bootstrap failed while preparing password authentication.',
                    previous: $exception,
                );
            } finally {
                $this->disconnect(
                    $ssh,
                );
            }

            try {
                $this->passwordRotation->verifyCredential(
                    server: $server,
                    password: $newPassword,
                );
            } catch (Throwable $exception) {
                throw new SSHKeyBootstrapException(
                    message: 'SSH key bootstrap changed the password but password authentication could not be verified.',
                    previous: $exception,
                );
            }
        }

        $this->removeBootstrapKeyUsingPassword(
            server: $server,
            password: $newPassword,
            publicKeyBlob: $publicKeyBlob,
        );
    }

    private function assertBootstrapInput(
        Server $server,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        if ($server->authentication_type !== AuthenticationType::SSHKey) {
            throw new SSHKeyBootstrapException(
                'SSH key bootstrap requires an SSH-key-authenticated server.',
            );
        }

        if (strtolower(trim($server->username)) !== 'root') {
            throw new SSHKeyBootstrapException(
                'SSH key bootstrap currently requires root SSH access.',
            );
        }

        if ($newPassword === '') {
            throw new SSHKeyBootstrapException(
                'SSH key bootstrap requires a new password.',
            );
        }
    }

    private function passwordAlreadyWorks(
        Server $server,
        #[SensitiveParameter]
        string $password,
    ): bool {
        try {
            $this->passwordRotation->verifyCredential(
                server: $server,
                password: $password,
            );

            return true;
        } catch (SSHPasswordRotationException) {
            return false;
        }
    }

    private function connectWithKey(
        string $host,
        Server $server,
        #[SensitiveParameter]
        string $privateKey,
    ): SSH2 {
        try {
            $key = PublicKeyLoader::loadPrivateKey(
                $privateKey,
            );
        } catch (Throwable $exception) {
            throw new SSHKeyBootstrapException(
                message: 'SSH key bootstrap credential could not be loaded.',
                previous: $exception,
            );
        }

        $ssh = new SSH2(
            host: $host,
            port: $server->port,
            timeout: SSHTimeout::CONNECTION,
        );

        $ssh->setTimeout(
            SSHTimeout::AUTHENTICATION,
        );

        if (! $ssh->login($server->username, $key)) {
            throw new SSHKeyBootstrapException(
                'SSH key authentication failed during bootstrap.',
            );
        }

        return $ssh;
    }

    private function connectWithPassword(
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

        if (! $ssh->login($server->username, $password)) {
            throw new SSHKeyBootstrapException(
                'Password authentication failed while removing the bootstrap SSH key.',
            );
        }

        return $ssh;
    }

    private function enablePasswordAuthentication(SSH2 $ssh): void
    {
        $ssh->setTimeout(
            SSHTimeout::NORMAL,
        );

        /*
         * Ubuntu cloud images commonly ship a 50-cloud-init.conf drop-in that
         * disables password authentication. OpenSSH uses the first obtained
         * value for these directives, so Coreflare deliberately installs a
         * lexically earlier drop-in instead of editing provider-owned files.
         */
        $command = <<<'BASH'
set -eu
install -d -o root -g root -m 0755 /etc/ssh/sshd_config.d
tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT
printf '%s\n' 'PasswordAuthentication yes' 'PermitRootLogin yes' > "$tmp"
install -o root -g root -m 0644 "$tmp" /etc/ssh/sshd_config.d/00-coreflare-password-auth.conf
sshd -t
if systemctl reload ssh 2>/dev/null; then
    :
elif systemctl reload sshd 2>/dev/null; then
    :
else
    exit 1
fi
printf '__coreflare_password_auth_ready__'
BASH;

        $output = $ssh->exec(
            $command,
        );

        if (
            ! is_string($output)
            || ! str_contains(
                $output,
                self::PASSWORD_AUTH_READY_MARKER,
            )
        ) {
            throw new SSHKeyBootstrapException(
                'SSH password authentication could not be enabled safely.',
            );
        }
    }

    private function changeRootPassword(
        SSH2 $ssh,
        #[SensitiveParameter]
        string $newPassword,
    ): void {
        $ssh->setTimeout(
            SSHTimeout::NORMAL,
        );

        /*
         * Keep the secret out of command strings and structured SSH logs by
         * using passwd's interactive prompts, matching managed password
         * rotation elsewhere in the SSH infrastructure.
         */
        $ssh->write(
            "passwd\n",
        );

        $this->waitForPrompt(
            ssh: $ssh,
            pattern: '/New password:\s*/i',
            errorMessage: 'Expected new-password prompt was not received during SSH key bootstrap.',
        );

        $ssh->write(
            $newPassword."\n",
        );

        $this->waitForPrompt(
            ssh: $ssh,
            pattern: '/Retype new password:\s*/i',
            errorMessage: 'Expected password confirmation prompt was not received during SSH key bootstrap.',
        );

        $ssh->write(
            $newPassword."\n",
        );

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
            throw new SSHKeyBootstrapException(
                'Remote server did not confirm the bootstrap password change.',
            );
        }
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
            throw new SSHKeyBootstrapException(
                $errorMessage,
            );
        }
    }

    private function removeBootstrapKeyUsingPassword(
        Server $server,
        #[SensitiveParameter]
        string $password,
        string $publicKeyBlob,
    ): void {
        $host = $this->targetPolicy->resolve(
            $server->host,
        );

        $ssh = $this->connectWithPassword(
            host: $host,
            server: $server,
            password: $password,
        );

        try {
            $ssh->setTimeout(
                SSHTimeout::NORMAL,
            );

            $command = sprintf(
                <<<'BASH'
set -eu
file=/root/.ssh/authorized_keys
if [ -f "$file" ]; then
    tmp="$(mktemp)"
    trap 'rm -f "$tmp"' EXIT
    awk -v key="%s" '{ keep=1; for (i=1; i<=NF; i++) if ($i==key) keep=0; if (keep) print }' "$file" > "$tmp"
    install -o root -g root -m 0600 "$tmp" "$file"
    if grep -Fq "%s" "$file"; then
        exit 1
    fi
fi
printf '__coreflare_bootstrap_key_removed__'
BASH,
                $publicKeyBlob,
                $publicKeyBlob,
            );

            $output = $ssh->exec(
                $command,
            );

            if (
                ! is_string($output)
                || ! str_contains(
                    $output,
                    self::KEY_REMOVED_MARKER,
                )
            ) {
                throw new SSHKeyBootstrapException(
                    'Bootstrap SSH key could not be removed after password verification.',
                );
            }
        } finally {
            $this->disconnect(
                $ssh,
            );
        }
    }

    private function publicKeyBlob(string $publicKey): string
    {
        $parts = preg_split(
            '/\s+/',
            trim($publicKey),
        );

        $blob = is_array($parts)
            ? ($parts[1] ?? null)
            : null;

        if (
            ! is_string($blob)
            || $blob === ''
            || preg_match('/\A[A-Za-z0-9+\/=]+\z/', $blob) !== 1
        ) {
            throw new SSHKeyBootstrapException(
                'Bootstrap SSH public key could not be normalized for removal.',
            );
        }

        return $blob;
    }

    private function disconnect(SSH2 $ssh): void
    {
        try {
            $ssh->disconnect();
        } catch (Throwable) {
            // Best-effort cleanup only.
        }
    }
}
