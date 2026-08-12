<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\N8n\PublicEndpoint;

use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Application\N8n\PublicEndpoint\DTOs\N8nRuntimeConfiguration;
use App\Infrastructure\Application\N8n\PublicEndpoint\DTOs\N8nRuntimeMutation;
use App\Support\SSH\SSHTimeout;

final readonly class SshN8nPublicEndpointRuntimeManager
{
    private const int ENVIRONMENT_UNAVAILABLE = 70;

    private const int CANDIDATE_INVALID = 71;

    private const int MUTATION_FAILED = 72;

    private const int VERIFICATION_FAILED = 73;

    private const int RECOVERY_FAILED = 74;

    private const int BUSY = 75;

    private const string INSPECT_COMMAND = <<<'BASH'
env_file='/opt/n8n/.env'

if [ ! -r "$env_file" ] || [ -L "$env_file" ]; then
    exit 70
fi

read_value() {
    wanted_key="$1"

    awk -v wanted_key="$wanted_key" '
function trim(value) {
    sub(/^[[:space:]]+/, "", value)
    sub(/[[:space:]]+$/, "", value)
    return value
}
{
    line = $0
    sub(/^[[:space:]]*export[[:space:]]+/, "", line)
    separator = index(line, "=")
    if (separator == 0) next
    key = trim(substr(line, 1, separator - 1))
    if (key != wanted_key) next
    value = trim(substr(line, separator + 1))
    first = substr(value, 1, 1)
    last = substr(value, length(value), 1)
    sq = sprintf("%c", 39)
    if (length(value) >= 2 && ((first == "\"" && last == "\"") || (first == sq && last == sq))) {
        value = substr(value, 2, length(value) - 2)
    }
    result = value
}
END { print result }
' "$env_file"
}

printf 'host=%s\n' "$(read_value 'N8N_HOST')"
printf 'protocol=%s\n' "$(read_value 'N8N_PROTOCOL')"
printf 'webhook_url=%s\n' "$(read_value 'N8N_WEBHOOK_URL')"
printf 'editor_base_url=%s\n' "$(read_value 'N8N_EDITOR_BASE_URL')"
printf 'proxy_hops=%s\n' "$(read_value 'N8N_PROXY_HOPS')"
printf 'legacy_webhook_url=%s\n' "$(read_value 'WEBHOOK_URL')"
BASH;

    private const string MUTATE_COMMAND = <<<'BASH'
set -u

mode=__XDEPLOY_MODE__
domain=__XDEPLOY_DOMAIN__
app_dir='/opt/n8n'
compose_file="$app_dir/docker-compose.yml"
env_file="$app_dir/.env"
backup_root="$app_dir/.xdeploy-backups/public-endpoint"
transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"
lock_file='/var/lock/xdeploy-n8n-public-endpoint.lock'

candidate_dir=''
backup_dir=''
mutation_started=0
workflow_finished=0

emit_failure() {
    stage="$1"
    restored="$2"
    recovered="$3"
    printf 'xdeploy_n8n_endpoint_runtime=1\n'
    printf 'status=failed\n'
    printf 'stage=%s\n' "$stage"
    printf 'configuration_restored=%s\n' "$restored"
    printf 'services_recovered=%s\n' "$recovered"
}

compose() {
    docker compose --env-file "$env_file" -f "$compose_file" -p n8n "$@"
}

container_running() {
    container_id="$(compose ps -q n8n 2>/dev/null)"
    [ -n "$container_id" ] &&
        [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" = 'true' ]
}

local_runtime_ready() {
    http_code="$(
        curl --silent --show-error --output /dev/null \
            --write-out '%{http_code}' --connect-timeout 3 --max-time 8 \
            'http://127.0.0.1:5678/' 2>/dev/null
    )"
    case "$http_code" in
        2??|3??) return 0 ;;
        *) return 1 ;;
    esac
}

atomic_restore() {
    source_file="$1"
    destination_file="$2"
    temporary_file="${destination_file}.xdeploy-restore.$$"
    cp -p "$source_file" "$temporary_file" && mv -f "$temporary_file" "$destination_file"
}

compensate() {
    restored=0
    recovered=0

    if [ -n "$backup_dir" ] && [ -r "$backup_dir/.env" ]; then
        if atomic_restore "$backup_dir/.env" "$env_file"; then
            restored=1
            if compose up -d --force-recreate >/dev/null 2>&1 && container_running && local_runtime_ready; then
                recovered=1
            fi
        fi
    fi

    if [ "$restored" -eq 1 ] && [ "$recovered" -eq 1 ]; then
        rm -f "$transaction_file"
        rm -rf "$backup_dir"
    fi

    printf '%s|%s' "$restored" "$recovered"
}

cleanup() {
    [ -z "$candidate_dir" ] || [ ! -d "$candidate_dir" ] || rm -rf "$candidate_dir"
}

on_exit() {
    status="$?"
    trap - EXIT HUP INT TERM
    if [ "$mutation_started" -eq 1 ] && [ "$workflow_finished" -eq 0 ]; then
        compensate >/dev/null 2>&1 || true
    fi
    cleanup
    exit "$status"
}

trap on_exit EXIT HUP INT TERM
umask 077

for command in awk chmod cmp cp curl docker flock grep install mkdir mktemp mv rm sleep; do
    command -v "$command" >/dev/null 2>&1 || {
        emit_failure environment 0 0
        exit 70
    }
done

if ! docker compose version >/dev/null 2>&1 ||
    [ ! -d "$app_dir" ] ||
    [ ! -r "$compose_file" ] ||
    [ ! -r "$env_file" ] ||
    [ -L "$compose_file" ] ||
    [ -L "$env_file" ]; then
    emit_failure environment 0 0
    exit 70
fi

case "$mode" in
    enable|disable) ;;
    *) emit_failure candidate 0 0; exit 71 ;;
esac

exec 9>"$lock_file" || { emit_failure environment 0 0; exit 70; }
if ! flock -n 9; then
    emit_failure busy 0 0
    exit 75
fi

if [ -e "$transaction_file" ] || [ -L "$transaction_file" ]; then
    emit_failure busy 0 0
    exit 75
fi

candidate_dir="$(mktemp -d "$app_dir/.xdeploy-endpoint.XXXXXX")" || {
    emit_failure candidate 0 0
    exit 71
}
candidate_env="$candidate_dir/.env"

if ! awk -v mode="$mode" -v domain="$domain" '
function environment_key(line, normalized) {
    normalized = line
    sub(/^[[:space:]]*export[[:space:]]+/, "", normalized)
    sub(/[[:space:]]*=.*/, "", normalized)
    return normalized
}
{
    key = environment_key($0)
    if (key == "N8N_HOST" ||
        key == "N8N_PORT" ||
        key == "N8N_PROTOCOL" ||
        key == "N8N_EDITOR_BASE_URL" ||
        key == "N8N_WEBHOOK_URL" ||
        key == "N8N_PROXY_HOPS" ||
        key == "WEBHOOK_URL") {
        next
    }
    print
}
END {
    if (mode == "enable") {
        print "N8N_HOST=" domain
        print "N8N_PORT=5678"
        print "N8N_PROTOCOL=https"
        print "N8N_EDITOR_BASE_URL=https://" domain "/"
        print "N8N_WEBHOOK_URL=https://" domain "/"
        print "N8N_PROXY_HOPS=1"
    }
}
' "$env_file" >"$candidate_env"; then
    emit_failure candidate 0 0
    exit 71
fi

if grep -Eq '^[[:space:]]*WEBHOOK_URL[[:space:]]*=' "$candidate_env"; then
    emit_failure candidate 0 0
    exit 71
fi

if [ "$mode" = 'enable' ]; then
    grep -Fxq "N8N_HOST=$domain" "$candidate_env" &&
    grep -Fxq 'N8N_PORT=5678' "$candidate_env" &&
    grep -Fxq 'N8N_PROTOCOL=https' "$candidate_env" &&
    grep -Fxq "N8N_EDITOR_BASE_URL=https://$domain/" "$candidate_env" &&
    grep -Fxq "N8N_WEBHOOK_URL=https://$domain/" "$candidate_env" &&
    grep -Fxq 'N8N_PROXY_HOPS=1' "$candidate_env" || {
        emit_failure candidate 0 0
        exit 71
    }
fi

if ! docker compose --env-file "$candidate_env" -f "$compose_file" -p n8n config --quiet >/dev/null 2>&1; then
    emit_failure candidate 0 0
    exit 71
fi

if cmp -s "$candidate_env" "$env_file"; then
    workflow_finished=1
    printf 'xdeploy_n8n_endpoint_runtime=1\n'
    printf 'status=prepared\n'
    printf 'backup_token=\n'
    printf 'configuration_changed=0\n'
    exit 0
fi

mkdir -p "$backup_root" || { emit_failure environment 0 0; exit 70; }
backup_dir="$(mktemp -d "$backup_root/runtime.XXXXXX")" || { emit_failure environment 0 0; exit 70; }
cp -p "$env_file" "$backup_dir/.env" || { emit_failure environment 0 0; exit 70; }

backup_token="${backup_dir##*/}"
transaction_new="${transaction_file}.xdeploy-new.$$"
printf '%s\n' "$backup_token" >"$transaction_new" &&
    chmod 600 "$transaction_new" &&
    mv -f "$transaction_new" "$transaction_file" || {
        emit_failure environment 0 0
        exit 70
    }

mutation_started=1

install -m 600 "$candidate_env" "${env_file}.xdeploy-new.$$" &&
    mv -f "${env_file}.xdeploy-new.$$" "$env_file" || {
        recovery="$(compensate)"
        workflow_finished=1
        emit_failure mutation "${recovery%%|*}" "${recovery#*|}"
        exit 72
    }

if ! compose up -d --force-recreate >/dev/null 2>&1; then
    recovery="$(compensate)"
    workflow_finished=1
    emit_failure mutation "${recovery%%|*}" "${recovery#*|}"
    exit 72
fi

verified=0
attempt=1
while [ "$attempt" -le 15 ]; do
    if container_running && local_runtime_ready; then
        verified=1
        break
    fi
    sleep 2
    attempt=$((attempt + 1))
done

if [ "$verified" -ne 1 ]; then
    recovery="$(compensate)"
    workflow_finished=1
    restored="${recovery%%|*}"
    recovered="${recovery#*|}"
    emit_failure verification "$restored" "$recovered"
    if [ "$restored" -eq 1 ] && [ "$recovered" -eq 1 ]; then
        exit 73
    fi
    exit 74
fi

workflow_finished=1
printf 'xdeploy_n8n_endpoint_runtime=1\n'
printf 'status=prepared\n'
printf 'backup_token=%s\n' "$backup_token"
printf 'configuration_changed=1\n'
BASH;

    private const string RESTORE_COMMAND = <<<'BASH'
set -u

token=__XDEPLOY_BACKUP_TOKEN__
app_dir='/opt/n8n'
compose_file="$app_dir/docker-compose.yml"
env_file="$app_dir/.env"
backup_root="$app_dir/.xdeploy-backups/public-endpoint"
transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"
lock_file='/var/lock/xdeploy-n8n-public-endpoint.lock'
backup_dir="$backup_root/$token"

case "$token" in
    runtime.[A-Za-z0-9]* ) ;;
    *) exit 74 ;;
esac

exec 9>"$lock_file" || exit 74
flock -n 9 || exit 75

if [ ! -r "$transaction_file" ] || [ -L "$transaction_file" ] ||
    [ "$(tr -d '\r\n' <"$transaction_file")" != "$token" ] ||
    [ ! -r "$backup_dir/.env" ]; then
    exit 74
fi

temporary="${env_file}.xdeploy-restore.$$"
cp -p "$backup_dir/.env" "$temporary" && mv -f "$temporary" "$env_file" || exit 74

docker compose --env-file "$env_file" -f "$compose_file" -p n8n \
    up -d --force-recreate >/dev/null 2>&1 || exit 74

container_id="$(docker compose --env-file "$env_file" -f "$compose_file" -p n8n ps -q n8n 2>/dev/null)"
[ -n "$container_id" ] || exit 74
[ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" = 'true' ] || exit 74

rm -f "$transaction_file"
rm -rf "$backup_dir"
BASH;

    private const string COMMIT_COMMAND = <<<'BASH'
set -u

token=__XDEPLOY_BACKUP_TOKEN__
app_dir='/opt/n8n'
backup_root="$app_dir/.xdeploy-backups/public-endpoint"
transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"
lock_file='/var/lock/xdeploy-n8n-public-endpoint.lock'
backup_dir="$backup_root/$token"

case "$token" in
    runtime.[A-Za-z0-9]* ) ;;
    *) exit 74 ;;
esac

exec 9>"$lock_file" || exit 74
flock -n 9 || exit 75

if [ ! -r "$transaction_file" ] || [ -L "$transaction_file" ] ||
    [ "$(tr -d '\r\n' <"$transaction_file")" != "$token" ]; then
    exit 74
fi

rm -f "$transaction_file"
rm -rf "$backup_dir"
BASH;

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function inspect(): N8nRuntimeConfiguration
    {
        $result = $this->privileged->executeWithResult(
            command: self::INSPECT_COMMAND,
            timeout: SSHTimeout::QUICK,
        );

        if (! $result->successful()) {
            throw PublicEndpointOperationException::environmentUnavailable();
        }

        $values = $this->parseKeyValues($result->output);

        return new N8nRuntimeConfiguration(
            host: $this->nullable($values['host'] ?? null),
            protocol: $this->nullable($values['protocol'] ?? null),
            webhookUrl: $this->nullable($values['webhook_url'] ?? null),
            editorBaseUrl: $this->nullable($values['editor_base_url'] ?? null),
            proxyHops: $this->nullable($values['proxy_hops'] ?? null),
            legacyWebhookUrl: $this->nullable($values['legacy_webhook_url'] ?? null),
        );
    }

    public function prepareEnabled(PublicEndpointDomain $domain): N8nRuntimeMutation
    {
        return $this->mutate('enable', $domain->value);
    }

    public function prepareDisabled(): N8nRuntimeMutation
    {
        return $this->mutate('disable', '');
    }

    public function restore(N8nRuntimeMutation $mutation): bool
    {
        if (! $mutation->configurationChanged || $mutation->backupToken === null) {
            return true;
        }

        $result = $this->privileged->executeWithResult(
            command: str_replace(
                '__XDEPLOY_BACKUP_TOKEN__',
                escapeshellarg($mutation->backupToken),
                self::RESTORE_COMMAND,
            ),
            timeout: SSHTimeout::APPLICATION_INSTALL,
        );

        return $result->successful();
    }

    public function commit(N8nRuntimeMutation $mutation): void
    {
        if (! $mutation->configurationChanged || $mutation->backupToken === null) {
            return;
        }

        $result = $this->privileged->executeWithResult(
            command: str_replace(
                '__XDEPLOY_BACKUP_TOKEN__',
                escapeshellarg($mutation->backupToken),
                self::COMMIT_COMMAND,
            ),
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw PublicEndpointOperationException::mutationFailed();
        }
    }

    private function mutate(string $mode, string $domain): N8nRuntimeMutation
    {
        $command = str_replace(
            ['__XDEPLOY_MODE__', '__XDEPLOY_DOMAIN__'],
            [escapeshellarg($mode), escapeshellarg($domain)],
            self::MUTATE_COMMAND,
        );

        $result = $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::APPLICATION_INSTALL,
        );

        $values = $this->parseKeyValues($result->output);

        if (! $result->successful()) {
            $recoveryAttempted = ($values['configuration_restored'] ?? '0') === '1'
                || ($values['services_recovered'] ?? '0') === '1';
            $recovered = ($values['configuration_restored'] ?? '0') === '1'
                && ($values['services_recovered'] ?? '0') === '1';

            throw match ($result->exitCode) {
                self::ENVIRONMENT_UNAVAILABLE => PublicEndpointOperationException::environmentUnavailable(),
                self::CANDIDATE_INVALID => PublicEndpointOperationException::candidateValidationFailed(),
                self::BUSY => PublicEndpointOperationException::operationInProgress(),
                self::VERIFICATION_FAILED,
                self::RECOVERY_FAILED => PublicEndpointOperationException::verificationFailed($recovered),
                default => PublicEndpointOperationException::mutationFailed(
                    recoveryAttempted: $recoveryAttempted,
                    recovered: $recovered,
                ),
            };
        }

        if (($values['xdeploy_n8n_endpoint_runtime'] ?? null) !== '1'
            || ($values['status'] ?? null) !== 'prepared') {
            throw PublicEndpointOperationException::mutationFailed();
        }

        $changed = ($values['configuration_changed'] ?? '0') === '1';
        $token = $this->nullable($values['backup_token'] ?? null);

        if ($changed && $token === null) {
            throw PublicEndpointOperationException::mutationFailed();
        }

        return new N8nRuntimeMutation(
            backupToken: $token,
            configurationChanged: $changed,
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

    private function nullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
