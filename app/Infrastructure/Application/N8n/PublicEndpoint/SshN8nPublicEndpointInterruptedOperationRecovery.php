<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\N8n\PublicEndpoint;

use App\Domain\Application\N8n\PublicEndpoint\N8nPublicEndpointInterruptedOperationRecovery;
use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Support\SSH\SSHTimeout;
use Illuminate\Support\Facades\Log;

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

readiness_attempts=0
readiness_http_code=''
readiness_container_running=0

emit_result() {
    interrupted="$1"
    recovered="$2"
    stage="$3"

    printf 'xdeploy_n8n_interrupted_recovery=1\n'
    printf 'interrupted=%s\n' "$interrupted"
    printf 'recovered=%s\n' "$recovered"
    printf 'stage=%s\n' "$stage"
    printf 'readiness_attempts=%s\n' "$readiness_attempts"
    printf 'readiness_http_code=%s\n' "$readiness_http_code"
    printf 'readiness_container_running=%s\n' "$readiness_container_running"
}

compose() {
    docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -p n8n \
        "$@"
}

container_running() {
    container_id="$(compose ps -q n8n 2>/dev/null)"

    [ -n "$container_id" ] &&
        [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" = 'true' ]
}

runtime_readiness_probe() {
    readiness_http_code=''
    readiness_container_running=0

    if ! container_running; then
        return 1
    fi

    readiness_container_running=1
    readiness_http_code="$(
        curl --silent --show-error --output /dev/null \
            --write-out '%{http_code}' \
            --connect-timeout 1 \
            --max-time 1 \
            'http://127.0.0.1:5678/healthz/readiness' \
            2>/dev/null || true
    )"

    [ "$readiness_http_code" = '200' ]
}

wait_for_runtime_readiness() {
    readiness_attempts=0
    readiness_http_code=''
    readiness_container_running=0
    attempt=1

    while [ "$attempt" -le 30 ]; do
        readiness_attempts="$attempt"

        if runtime_readiness_probe; then
            return 0
        fi

        if [ "$attempt" -lt 30 ]; then
            sleep 2
        fi

        attempt=$((attempt + 1))
    done

    return 1
}

exec 9>"$lock_file" || {
    emit_result 1 0 lock
    exit 74
}

if ! flock -n 9; then
    emit_result 1 0 busy
    exit 75
fi

if [ ! -e "$transaction_file" ] && [ ! -L "$transaction_file" ]; then
    emit_result 0 1 none
    exit 0
fi

if [ -L "$transaction_file" ] || [ ! -r "$transaction_file" ]; then
    emit_result 1 0 transaction
    exit 74
fi

token="$(tr -d '\r\n' <"$transaction_file")"

case "$token" in
    runtime.[A-Za-z0-9]* ) ;;
    * )
        emit_result 1 0 transaction
        exit 74
        ;;
esac

backup_dir="$backup_root/$token"

if [ ! -r "$backup_dir/.env" ] || [ -L "$backup_dir/.env" ]; then
    emit_result 1 0 backup
    exit 74
fi

temporary="${env_file}.xdeploy-interrupted-restore.$$"

if ! cp -p "$backup_dir/.env" "$temporary" ||
    ! mv -f "$temporary" "$env_file"; then
    rm -f "$temporary"
    emit_result 1 0 restore
    exit 74
fi

if ! compose up -d --force-recreate >/dev/null 2>&1; then
    emit_result 1 0 recreate
    exit 74
fi

if ! wait_for_runtime_readiness; then
    emit_result 1 0 readiness
    exit 74
fi

rm -f "$transaction_file"
rm -rf "$backup_dir"

emit_result 1 1 completed
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

        $values = $this->parseKeyValues($result->output);

        Log::error('public_endpoint.n8n.interrupted_recovery_failed', [
            'stage' => $this->diagnosticStage($values['stage'] ?? null),
            'exit_code' => $result->exitCode,
            'interrupted' => ($values['interrupted'] ?? '0') === '1',
            'recovered' => ($values['recovered'] ?? '0') === '1',
            'readiness_attempts' => $this->diagnosticInteger(
                $values['readiness_attempts'] ?? null,
            ),
            'readiness_http_code' => $this->diagnosticHttpCode(
                $values['readiness_http_code'] ?? null,
            ),
            'readiness_container_running' => ($values['readiness_container_running'] ?? '0') === '1',
        ]);

        throw PublicEndpointOperationException::mutationFailed(
            recoveryAttempted: true,
            recovered: false,
        );
    }

    /** @return array<string, string> */
    private function parseKeyValues(string $output): array
    {
        $values = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            [$key, $value] = array_pad(explode('=', trim($line), 2), 2, '');

            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private function diagnosticStage(?string $value): string
    {
        return in_array(
            $value,
            [
                'lock',
                'busy',
                'transaction',
                'backup',
                'restore',
                'recreate',
                'readiness',
                'completed',
                'none',
            ],
            true,
        ) ? $value : 'unknown';
    }

    private function diagnosticInteger(?string $value): int
    {
        if ($value === null || preg_match('/^\d{1,4}$/', $value) !== 1) {
            return 0;
        }

        return min((int) $value, 9999);
    }

    private function diagnosticHttpCode(?string $value): ?string
    {
        if ($value === null || preg_match('/^\d{3}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
