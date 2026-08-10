<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Admin\DTOs\MarzbanAdminOverview;
use App\Domain\Application\Marzban\Admin\MarzbanAdminGateway;
use App\Domain\Application\Marzban\Admin\MarzbanAdminReader;
use App\Domain\Application\Marzban\Exceptions\MarzbanAdminProvisioningException;
use App\Domain\Application\Marzban\Exceptions\MarzbanSetupInspectionException;
use App\Domain\Application\Marzban\Setup\Enums\MarzbanSetupState;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Application\Marzban\Parsers\MarzbanAdminListParser;
use App\Support\SSH\SSHTimeout;
use Throwable;

final readonly class SshMarzbanAdminGateway implements MarzbanAdminGateway, MarzbanAdminReader
{
    private const string INSPECT_COMMAND = <<<'BASH'
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
    -e COLUMNS=320 \
    marzban \
    marzban-cli admin list \
    --limit 1000{{ username_option }} \
    2>&1
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

    public function overview(): MarzbanAdminOverview
    {
        return $this->inspectAdmins();
    }

    public function inspect(
        ?string $username = null,
    ): MarzbanSetupState {
        return $this->inspectAdmins(
            $username,
        )->state;
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
            throw MarzbanAdminProvisioningException::commandFailed();
        }

        if (! $result->successful()) {
            throw MarzbanAdminProvisioningException::commandFailed();
        }
    }

    private function inspectAdmins(
        ?string $username = null,
    ): MarzbanAdminOverview {
        try {
            $result = $this->privileged->executeWithResult(
                command: $this->inspectionCommand(
                    $username,
                ),
                timeout: SSHTimeout::QUICK,
            );
        } catch (Throwable) {
            throw MarzbanSetupInspectionException::failed();
        }

        if (! $result->successful()) {
            throw MarzbanSetupInspectionException::failed();
        }

        return (new MarzbanAdminListParser)
            ->parse(
                $result->output,
            );
    }

    private function inspectionCommand(
        ?string $username,
    ): string {
        $usernameOption = $username === null
            ? ''
            : ' --username '
                .$this->shellArgument(
                    $username,
                );

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

    private function shellArgument(
        string $value,
    ): string {
        return "'".str_replace(
            "'",
            "'\"'\"'",
            $value,
        )."'";
    }
}
