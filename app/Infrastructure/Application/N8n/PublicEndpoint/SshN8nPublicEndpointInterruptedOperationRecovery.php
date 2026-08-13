<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\N8n\PublicEndpoint;

use App\Domain\Application\N8n\PublicEndpoint\N8nPublicEndpointInterruptedOperationRecovery;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Support\SSH\SSHTimeout;

final readonly class SshN8nPublicEndpointInterruptedOperationRecovery implements N8nPublicEndpointInterruptedOperationRecovery
{
    private const int BUSY = 75;

    private const string RECOVER_COMMAND = <<<'BASH'
set -u

app_dir='/opt/n8n'
compose_file="$app_dir/docker-compose.yml"
env_file="$app_dir/.env"
backup_root="$app_dir/.xdeploy-backups/public-endpoint"
transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"
lock_file='/var/lock/xdeploy-n8n-public-endpoint.lock'

printf 'xdeploy_n8n_interrupted_recovery=1\n'

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

temporary="${env_file}.xdeploy-interrupted-restore.$$"

if ! cp -p "$backup_dir/.env" "$temporary" ||
    ! mv -f "$temporary" "$env_file"; then
    rm -f "$temporary"
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

if ! docker compose \
    --env-file "$env_file" \
    -f "$compose_file" \
    -p n8n \
    up -d --force-recreate >/dev/null 2>&1; then
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

container_id="$(
    docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -p n8n \
        ps -q n8n 2>/dev/null
)"

if [ -z "$container_id" ] ||
    [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" != 'true' ]; then
    printf 'interrupted=1\nrecovered=0\n'
    exit 74
fi

http_code="$(
    curl \
        --silent \
        --show-error \
        --output /dev/null \
        --write-out '%{http_code}' \
        --connect-timeout 3 \
        --max-time 8 \
        'http://127.0.0.1:5678/' 2>/dev/null
)"

case "$http_code" in
    2??|3??) ;;
    *)
        printf 'interrupted=1\nrecovered=0\n'
        exit 74
        ;;
esac

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
            throw PublicEndpointOperationException::operationInProgress();
        }

        throw PublicEndpointOperationException::mutationFailed(
            recoveryAttempted: true,
            recovered: false,
        );
    }
}
