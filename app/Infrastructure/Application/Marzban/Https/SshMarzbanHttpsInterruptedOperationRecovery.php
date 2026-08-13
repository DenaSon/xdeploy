<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Https;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsRecoveryResult;
use App\Domain\Application\Marzban\Https\MarzbanHttpsInterruptedOperationRecovery;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Support\SSH\SSHTimeout;

final readonly class SshMarzbanHttpsInterruptedOperationRecovery implements MarzbanHttpsInterruptedOperationRecovery
{
    private const int BUSY = 75;

    private const string RECOVER_COMMAND = <<<'BASH'
set -u

marzban_path='/opt/marzban'
compose_file="$marzban_path/docker-compose.yml"
env_file="$marzban_path/.env"
backup_root="$marzban_path/.xdeploy-backups/https-runtime"
transaction_file="$marzban_path/.xdeploy-https-runtime-transaction"
lock_file='/var/lock/xdeploy-marzban-https-runtime.lock'

printf 'xdeploy_marzban_interrupted_recovery=1\n'

exec 9>"$lock_file" || exit 74

if ! flock -n 9; then
    exit 75
fi

if [ ! -e "$transaction_file" ] && [ ! -L "$transaction_file" ]; then
    printf 'interrupted=0\nrecovered=1\n'
    exit 0
fi

if [ -L "$transaction_file" ] || [ ! -r "$transaction_file" ]; then
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

token="$(tr -d '\r\n' <"$transaction_file")"

case "$token" in
    runtime.[A-Za-z0-9]* ) ;;
    * )
        printf 'interrupted=1\nrecovered=0\n'
        exit 74
        ;;
esac

backup_dir="$backup_root/$token"

if [ ! -r "$backup_dir/.env" ] || [ -L "$backup_dir/.env" ]; then
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

temporary_file="${env_file}.xdeploy-interrupted-restore.$$"

if ! cp -p "$backup_dir/.env" "$temporary_file" ||
    ! mv -f "$temporary_file" "$env_file"; then
    rm -f "$temporary_file"
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

if ! docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    -p marzban \
    up -d --force-recreate >/dev/null 2>&1; then
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

container_id="$(
    docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -p marzban \
        ps -q marzban 2>/dev/null
)"

if [ -z "$container_id" ] ||
    [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" != 'true' ]; then
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

rm -f "$transaction_file"
rm -rf "$backup_dir"

printf 'interrupted=1\nrecovered=1\n'
BASH;

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function recover(): void
    {
        $result = $this->privileged->executeWithResult(
            command: self::RECOVER_COMMAND,
            timeout: SSHTimeout::APPLICATION_INSTALL,
            sensitive: true,
        );

        if ($result->successful()) {
            return;
        }

        if ($result->exitCode === self::BUSY) {
            throw MarzbanHttpsApplyException::operationInProgress();
        }

        throw MarzbanHttpsApplyException::mutationFailed(
            new MarzbanHttpsRecoveryResult(
                configurationRestored: false,
                servicesRecovered: false,
            ),
        );
    }
}
