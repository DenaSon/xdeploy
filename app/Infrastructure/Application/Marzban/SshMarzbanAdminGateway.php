<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Admin\MarzbanAdminGateway;
use App\Domain\Application\Marzban\Exceptions\MarzbanAdminProvisioningException;
use App\Domain\Application\Marzban\Exceptions\MarzbanSetupInspectionException;
use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Support\SSH\SSHTimeout;
use Throwable;

final readonly class SshMarzbanAdminGateway implements MarzbanAdminGateway
{
    private const string INSPECT_COMMAND = <<<'BASH'
output="$(NO_COLOR=1 TERM=dumb marzban cli admin list{{ username_option }} 2>&1)"
status=$?

if [ "$status" -ne 0 ]; then
    exit "$status"
fi

if printf '%s' "$output" | grep -q '✔'; then
    printf 'complete'
else
    printf 'pending'
fi
BASH;

    private const string CREATE_COMMAND = <<<'BASH'
if docker compose version >/dev/null 2>&1; then
    compose='docker compose'
elif docker-compose version >/dev/null 2>&1; then
    compose='docker-compose'
else
    exit 127
fi

$compose \
    -f /opt/marzban/docker-compose.yml \
    -p marzban \
    exec -T \
    -e CLI_PROG_NAME='marzban cli' \
    -e MARZBAN_ADMIN_PASSWORD={{ password }} \
    marzban \
    marzban-cli admin create \
    --username {{ username }} \
    --sudo \
    --telegram-id 0 \
    --discord-webhook 0
BASH;

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function inspect(
        ?string $username = null,
    ): MarzbanSetupState {
        try {
            $result = $this->privileged->executeWithResult(
                command: $this->inspectionCommand($username),
                timeout: SSHTimeout::QUICK,
            );
        } catch (Throwable) {
            throw MarzbanSetupInspectionException::failed();
        }

        if (! $result->successful()) {
            throw MarzbanSetupInspectionException::failed();
        }

        return MarzbanSetupState::tryFrom(
            trim($result->output),
        ) ?? throw MarzbanSetupInspectionException::failed();
    }

    public function create(
        string $username,
        string $password,
    ): void {
        try {
            $result = $this->privileged->executeWithResult(
                command: $this->creationCommand(
                    username: $username,
                    password: $password,
                ),
                timeout: SSHTimeout::NORMAL,
                sensitive: true,
            );
        } catch (Throwable) {
            /*
             * Do not attach the original exception because it may contain
             * information about the sensitive remote command.
             */
            throw MarzbanAdminProvisioningException::commandFailed();
        }

        if (! $result->successful()) {
            throw MarzbanAdminProvisioningException::commandFailed();
        }
    }

    private function inspectionCommand(
        ?string $username,
    ): string {
        $usernameOption = $username === null
            ? ''
            : ' --username '.$this->shellArgument($username);

        return strtr(
            self::INSPECT_COMMAND,
            [
                '{{ username_option }}' => $usernameOption,
            ],
        );
    }

    private function creationCommand(
        string $username,
        string $password,
    ): string {
        return strtr(
            self::CREATE_COMMAND,
            [
                '{{ username }}' => $this->shellArgument(
                    $username,
                ),
                '{{ password }}' => $this->shellArgument(
                    $password,
                ),
            ],
        );
    }

    /**
     * Quote a value for the remote POSIX shell.
     *
     * escapeshellarg() is deliberately not used because xDeploy may run
     * on Windows while the destination command is always executed by Bash.
     */
    private function shellArgument(string $value): string
    {
        return "'".str_replace(
            "'",
            "'\"'\"'",
            $value,
        )."'";
    }
}
