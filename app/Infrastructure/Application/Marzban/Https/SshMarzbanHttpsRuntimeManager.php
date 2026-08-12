<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Https;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsInspectionException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsRecoveryResult;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Application\Marzban\Https\DTOs\MarzbanHttpsRuntimeInfo;
use App\Infrastructure\Application\Marzban\Https\DTOs\MarzbanHttpsRuntimeMutation;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class SshMarzbanHttpsRuntimeManager
{
    private const int ENVIRONMENT_UNAVAILABLE = 70;

    private const int CANDIDATE_INVALID = 71;

    private const int MUTATION_FAILED = 72;

    private const int VERIFICATION_FAILED = 73;

    private const int RECOVERY_FAILED = 74;

    private const int BUSY = 75;

    private const string INSPECT_COMMAND = <<<'BASH'
env_file='/opt/marzban/.env'

if [ ! -r "$env_file" ] || [ -L "$env_file" ]; then
    exit 2
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

    if (separator == 0) {
        next
    }

    key = trim(substr(line, 1, separator - 1))

    if (key != wanted_key) {
        next
    }

    value = trim(substr(line, separator + 1))
    first = substr(value, 1, 1)
    last = substr(value, length(value), 1)
    single_quote = sprintf("%c", 39)

    if (length(value) >= 2 && ((first == "\"" && last == "\"") || (first == single_quote && last == single_quote))) {
        value = substr(value, 2, length(value) - 2)
    }

    result = value
}

END {
    print result
}
' "$env_file"
}

printf 'host=%s\n' "$(read_value 'UVICORN_HOST')"
printf 'port=%s\n' "$(read_value 'UVICORN_PORT')"
printf 'uds=%s\n' "$(read_value 'UVICORN_UDS')"
printf 'cert=%s\n' "$(read_value 'UVICORN_SSL_CERTFILE')"
printf 'key=%s\n' "$(read_value 'UVICORN_SSL_KEYFILE')"
printf 'subscription_url=%s\n' "$(read_value 'XRAY_SUBSCRIPTION_URL_PREFIX')"
BASH;

    private const string PREPARE_COMMAND = <<<'BASH'
set -u

domain=__XDEPLOY_DOMAIN__
marzban_path='/opt/marzban'
compose_file="$marzban_path/docker-compose.yml"
env_file="$marzban_path/.env"
backup_root="$marzban_path/.xdeploy-backups/https-runtime"
transaction_file="$marzban_path/.xdeploy-https-runtime-transaction"
lock_file='/var/lock/xdeploy-marzban-https-runtime.lock'

candidate_dir=''
backup_dir=''
mutation_started=0
workflow_finished=0

emit_failure() {
    stage="$1"
    restored="$2"
    recovered="$3"

    printf 'xdeploy_marzban_runtime=1\n'
    printf 'status=failed\n'
    printf 'stage=%s\n' "$stage"
    printf 'configuration_restored=%s\n' "$restored"
    printf 'services_recovered=%s\n' "$recovered"
}

compose() {
    docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -p marzban \
        "$@"
}

container_running() {
    container_id="$(compose ps -q marzban 2>/dev/null)"

    [ -n "$container_id" ] &&
        [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" = 'true' ]
}

local_runtime_ready() {
    http_code="$(
        curl \
            --silent \
            --show-error \
            --output /dev/null \
            --write-out '%{http_code}' \
            --connect-timeout 3 \
            --max-time 8 \
            'http://127.0.0.1:8000/dashboard/' 2>/dev/null
    )"

    case "$http_code" in
        2??|3??) return 0 ;;
        *) return 1 ;;
    esac
}

atomic_install() {
    source_file="$1"
    destination_file="$2"
    file_mode="$3"
    temporary_file="${destination_file}.xdeploy-new.$$"

    install -m "$file_mode" "$source_file" "$temporary_file" &&
        mv -f "$temporary_file" "$destination_file"
}

atomic_restore() {
    source_file="$1"
    destination_file="$2"
    temporary_file="${destination_file}.xdeploy-restore.$$"

    cp -p "$source_file" "$temporary_file" &&
        mv -f "$temporary_file" "$destination_file"
}

compensate() {
    configuration_restored=0
    services_recovered=0

    if [ -n "$backup_dir" ] && [ -r "$backup_dir/.env" ]; then
        if atomic_restore "$backup_dir/.env" "$env_file"; then
            configuration_restored=1

            if compose up -d --force-recreate >/dev/null 2>&1 &&
                container_running; then
                services_recovered=1
            fi
        fi
    fi

    if [ "$configuration_restored" -eq 1 ] &&
        [ "$services_recovered" -eq 1 ]; then
        rm -f "$transaction_file"
        rm -rf "$backup_dir"
    fi

    printf '%s|%s' "$configuration_restored" "$services_recovered"
}

cleanup() {
    if [ -n "$candidate_dir" ] && [ -d "$candidate_dir" ]; then
        rm -rf "$candidate_dir"
    fi
}

on_exit() {
    exit_status="$?"

    trap - EXIT HUP INT TERM

    if [ "$mutation_started" -eq 1 ] &&
        [ "$workflow_finished" -eq 0 ]; then
        compensate >/dev/null 2>&1 || true
    fi

    cleanup
    exit "$exit_status"
}

trap on_exit EXIT HUP INT TERM
umask 077

for required_command in \
    awk chmod cp curl docker flock grep install mkdir mktemp mv rm sed sleep; do
    if ! command -v "$required_command" >/dev/null 2>&1; then
        emit_failure 'environment' 0 0
        exit 70
    fi
done

if ! docker compose version >/dev/null 2>&1 ||
    [ ! -d "$marzban_path" ] ||
    [ ! -r "$compose_file" ] ||
    [ ! -r "$env_file" ] ||
    [ -L "$compose_file" ] ||
    [ -L "$env_file" ]; then
    emit_failure 'environment' 0 0
    exit 70
fi

exec 9>"$lock_file" || {
    emit_failure 'environment' 0 0
    exit 70
}

if ! flock -n 9; then
    emit_failure 'busy' 0 0
    exit 75
fi

if [ -e "$transaction_file" ] || [ -L "$transaction_file" ]; then
    emit_failure 'busy' 0 0
    exit 75
fi

candidate_dir="$(mktemp -d "$marzban_path/.xdeploy-https-runtime.XXXXXX")" || {
    emit_failure 'candidate' 0 0
    exit 71
}

candidate_env="$candidate_dir/.env"

if ! awk \
    -v host='127.0.0.1' \
    -v port='8000' \
    -v subscription_url="https://$domain" '
function environment_key(line, normalized) {
    normalized = line
    sub(/^[[:space:]]*export[[:space:]]+/, "", normalized)
    sub(/[[:space:]]*=.*/, "", normalized)

    return normalized
}

{
    key = environment_key($0)

    if (key == "UVICORN_UDS") {
        next
    }

    if (key == "UVICORN_SSL_CERTFILE" || key == "UVICORN_SSL_KEYFILE") {
        next
    }

    if (key == "UVICORN_HOST") {
        if (! host_written) {
            print "UVICORN_HOST=" host
            host_written = 1
        }

        next
    }

    if (key == "UVICORN_PORT") {
        if (! port_written) {
            print "UVICORN_PORT=" port
            port_written = 1
        }

        next
    }

    if (key == "XRAY_SUBSCRIPTION_URL_PREFIX") {
        if (! subscription_written) {
            print "XRAY_SUBSCRIPTION_URL_PREFIX=" subscription_url
            subscription_written = 1
        }

        next
    }

    print
}

END {
    if (! host_written) {
        print "UVICORN_HOST=" host
    }

    if (! port_written) {
        print "UVICORN_PORT=" port
    }

    if (! subscription_written) {
        print "XRAY_SUBSCRIPTION_URL_PREFIX=" subscription_url
    }
}
' "$env_file" >"$candidate_env"; then
    emit_failure 'candidate' 0 0
    exit 71
fi

if grep -Eq '^[[:space:]]*UVICORN_UDS[[:space:]]*=' "$candidate_env" ||
    grep -Eq '^[[:space:]]*UVICORN_SSL_CERTFILE[[:space:]]*=' "$candidate_env" ||
    grep -Eq '^[[:space:]]*UVICORN_SSL_KEYFILE[[:space:]]*=' "$candidate_env" ||
    ! grep -Fxq 'UVICORN_HOST=127.0.0.1' "$candidate_env" ||
    ! grep -Fxq 'UVICORN_PORT=8000' "$candidate_env" ||
    ! grep -Fxq "XRAY_SUBSCRIPTION_URL_PREFIX=https://$domain" "$candidate_env"; then
    emit_failure 'candidate' 0 0
    exit 71
fi

if ! docker compose \
    --env-file "$candidate_env" \
    -f "$compose_file" \
    -p marzban \
    config --quiet >/dev/null 2>&1; then
    emit_failure 'candidate' 0 0
    exit 71
fi

mkdir -p "$backup_root" || {
    emit_failure 'environment' 0 0
    exit 70
}

backup_dir="$(mktemp -d "$backup_root/runtime.XXXXXX")" || {
    emit_failure 'environment' 0 0
    exit 70
}

if ! cp -p "$env_file" "$backup_dir/.env"; then
    emit_failure 'environment' 0 0
    exit 70
fi

backup_token="${backup_dir##*/}"
transaction_new="${transaction_file}.xdeploy-new.$$"

if ! printf '%s\n' "$backup_token" >"$transaction_new" ||
    ! chmod 600 "$transaction_new" ||
    ! mv -f "$transaction_new" "$transaction_file"; then
    emit_failure 'environment' 0 0
    exit 70
fi

mutation_started=1

if ! atomic_install "$candidate_env" "$env_file" 600; then
    recovery="$(compensate)"
    workflow_finished=1
    emit_failure 'mutation' "${recovery%%|*}" "${recovery#*|}"
    exit 72
fi

if ! compose up -d --force-recreate >/dev/null 2>&1; then
    recovery="$(compensate)"
    workflow_finished=1
    emit_failure 'mutation' "${recovery%%|*}" "${recovery#*|}"
    exit 72
fi

verification_succeeded=0
attempt=1

while [ "$attempt" -le 12 ]; do
    if container_running && local_runtime_ready; then
        verification_succeeded=1
        break
    fi

    sleep 2
    attempt=$((attempt + 1))
done

if [ "$verification_succeeded" -ne 1 ]; then
    recovery="$(compensate)"
    workflow_finished=1
    restored="${recovery%%|*}"
    recovered="${recovery#*|}"

    emit_failure 'verification' "$restored" "$recovered"

    if [ "$restored" -eq 1 ] && [ "$recovered" -eq 1 ]; then
        exit 73
    fi

    exit 74
fi

workflow_finished=1

printf 'xdeploy_marzban_runtime=1\n'
printf 'status=prepared\n'
printf 'backup_token=%s\n' "$backup_token"
printf 'configuration_changed=1\n'
BASH;

    private const string RESTORE_COMMAND = <<<'BASH'
set -u

token=__XDEPLOY_BACKUP_TOKEN__
marzban_path='/opt/marzban'
compose_file="$marzban_path/docker-compose.yml"
env_file="$marzban_path/.env"
backup_root="$marzban_path/.xdeploy-backups/https-runtime"
transaction_file="$marzban_path/.xdeploy-https-runtime-transaction"
lock_file='/var/lock/xdeploy-marzban-https-runtime.lock'
backup_dir="$backup_root/$token"

printf 'xdeploy_marzban_runtime_restore=1\n'

case "$token" in
    runtime.[A-Za-z0-9]* ) ;;
    * )
        printf 'configuration_restored=0\nservices_recovered=0\n'
        exit 74
        ;;
esac

exec 9>"$lock_file" || {
    printf 'configuration_restored=0\nservices_recovered=0\n'
    exit 74
}

if ! flock -n 9; then
    printf 'configuration_restored=0\nservices_recovered=0\n'
    exit 75
fi

if [ ! -r "$transaction_file" ] ||
    [ -L "$transaction_file" ] ||
    [ "$(tr -d '\r\n' <"$transaction_file")" != "$token" ] ||
    [ ! -r "$backup_dir/.env" ]; then
    printf 'configuration_restored=0\nservices_recovered=0\n'
    exit 74
fi

temporary_file="${env_file}.xdeploy-restore.$$"
configuration_restored=0
services_recovered=0

if cp -p "$backup_dir/.env" "$temporary_file" &&
    mv -f "$temporary_file" "$env_file"; then
    configuration_restored=1

    if docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -p marzban \
        up -d --force-recreate >/dev/null 2>&1; then
        container_id="$(
            docker compose \
                --env-file "$env_file" \
                -f "$compose_file" \
                -p marzban \
                ps -q marzban 2>/dev/null
        )"

        if [ -n "$container_id" ] &&
            [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" = 'true' ]; then
            services_recovered=1
        fi
    fi
fi

if [ "$configuration_restored" -eq 1 ] &&
    [ "$services_recovered" -eq 1 ]; then
    rm -f "$transaction_file"
    rm -rf "$backup_dir"
fi

printf 'configuration_restored=%s\n' "$configuration_restored"
printf 'services_recovered=%s\n' "$services_recovered"

if [ "$configuration_restored" -eq 1 ] &&
    [ "$services_recovered" -eq 1 ]; then
    exit 0
fi

exit 74
BASH;

    private const string COMMIT_COMMAND = <<<'BASH'
token=__XDEPLOY_BACKUP_TOKEN__
marzban_path='/opt/marzban'
backup_root="$marzban_path/.xdeploy-backups/https-runtime"
transaction_file="$marzban_path/.xdeploy-https-runtime-transaction"
lock_file='/var/lock/xdeploy-marzban-https-runtime.lock'
backup_dir="$backup_root/$token"

case "$token" in
    runtime.[A-Za-z0-9]* ) ;;
    * ) exit 1 ;;
esac

exec 9>"$lock_file" || exit 1
flock -n 9 || exit 1

if [ ! -r "$transaction_file" ] ||
    [ -L "$transaction_file" ] ||
    [ "$(tr -d '\r\n' <"$transaction_file")" != "$token" ]; then
    exit 1
fi

rm -f "$transaction_file"
rm -rf "$backup_dir"
BASH;

    private const string VERIFY_HTTPS_COMMAND = <<<'BASH'
domain=__XDEPLOY_DOMAIN__
attempt=1

while [ "$attempt" -le 18 ]; do
    http_code="$(
        curl \
            --location \
            --silent \
            --show-error \
            --output /dev/null \
            --write-out '%{http_code}' \
            --connect-timeout 3 \
            --max-time 8 \
            "https://$domain/dashboard/" 2>/dev/null
    )"

    case "$http_code" in
        2??|3??) exit 0 ;;
    esac

    sleep 5
    attempt=$((attempt + 1))
done

exit 1
BASH;

    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function inspect(): MarzbanHttpsRuntimeInfo
    {
        $result = $this->privileged->executeWithResult(
            command: self::INSPECT_COMMAND,
            timeout: SSHTimeout::QUICK,
        );

        if (! $result->successful()) {
            throw MarzbanHttpsInspectionException::failed();
        }

        $values = $this->parseKeyValueOutput($result->output);
        $port = filter_var(
            $values['port'] ?? null,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                    'max_range' => 65535,
                ],
            ],
        );

        if ($port === false) {
            throw MarzbanHttpsInspectionException::failed();
        }

        return new MarzbanHttpsRuntimeInfo(
            host: trim($values['host'] ?? ''),
            port: (int) $port,
            uds: $this->nullable($values['uds'] ?? null),
            sslCertificateFile: $this->nullable($values['cert'] ?? null),
            sslKeyFile: $this->nullable($values['key'] ?? null),
            subscriptionUrl: $this->nullable(
                $values['subscription_url'] ?? null,
            ),
        );
    }

    public function prepare(
        MarzbanDomain $domain,
    ): MarzbanHttpsRuntimeMutation {
        $command = str_replace(
            '__XDEPLOY_DOMAIN__',
            escapeshellarg($domain->value),
            self::PREPARE_COMMAND,
        );

        $result = $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::APPLICATION_INSTALL,
            sensitive: true,
        );

        $values = $this->parseKeyValueOutput($result->output);

        if ($result->successful()) {
            $token = $values['backup_token'] ?? '';

            if (
                ($values['xdeploy_marzban_runtime'] ?? null) !== '1'
                || ($values['status'] ?? null) !== 'prepared'
                || ($values['configuration_changed'] ?? null) !== '1'
                || preg_match('/^runtime\.[A-Za-z0-9]+$/', $token) !== 1
            ) {
                throw MarzbanHttpsApplyException::mutationFailed();
            }

            return new MarzbanHttpsRuntimeMutation(
                backupToken: $token,
                configurationChanged: true,
            );
        }

        $recovery = ($values['xdeploy_marzban_runtime'] ?? null) === '1'
            ? $this->recoveryFromValues($values)
            : null;

        throw match ($result->exitCode) {
            self::ENVIRONMENT_UNAVAILABLE => MarzbanHttpsApplyException::environmentUnavailable(),
            self::CANDIDATE_INVALID => MarzbanHttpsApplyException::candidateValidationFailed(),
            self::BUSY => MarzbanHttpsApplyException::operationInProgress(),
            self::VERIFICATION_FAILED,
            self::RECOVERY_FAILED => MarzbanHttpsApplyException::verificationFailed(
                $recovery ?? new MarzbanHttpsRecoveryResult(
                    configurationRestored: false,
                    servicesRecovered: false,
                ),
            ),
            self::MUTATION_FAILED => MarzbanHttpsApplyException::mutationFailed($recovery),
            default => MarzbanHttpsApplyException::mutationFailed(),
        };
    }

    public function restore(
        MarzbanHttpsRuntimeMutation $mutation,
    ): MarzbanHttpsRecoveryResult {
        $command = str_replace(
            '__XDEPLOY_BACKUP_TOKEN__',
            escapeshellarg($mutation->backupToken),
            self::RESTORE_COMMAND,
        );

        $result = $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::APPLICATION_INSTALL,
            sensitive: true,
        );

        $values = $this->parseKeyValueOutput($result->output);

        return $this->recoveryFromValues($values)
            ?? new MarzbanHttpsRecoveryResult(
                configurationRestored: false,
                servicesRecovered: false,
            );
    }

    public function commit(
        MarzbanHttpsRuntimeMutation $mutation,
    ): void {
        $command = str_replace(
            '__XDEPLOY_BACKUP_TOKEN__',
            escapeshellarg($mutation->backupToken),
            self::COMMIT_COMMAND,
        );

        $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::NORMAL,
            sensitive: true,
        );
    }

    public function verifyHttps(
        MarzbanDomain $domain,
    ): bool {
        $command = str_replace(
            '__XDEPLOY_DOMAIN__',
            escapeshellarg($domain->value),
            self::VERIFY_HTTPS_COMMAND,
        );

        return $this->ssh->executeWithResult(
            command: $command,
            timeout: SSHTimeout::APPLICATION_INSTALL,
        )->successful();
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyValueOutput(string $output): array
    {
        $values = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            [$key, $value] = array_pad(
                explode('=', trim($line), 2),
                2,
                '',
            );

            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function recoveryFromValues(
        array $values,
    ): ?MarzbanHttpsRecoveryResult {
        $configurationRestored = match (
            $values['configuration_restored'] ?? null
        ) {
            '0' => false,
            '1' => true,
            default => null,
        };

        $servicesRecovered = match (
            $values['services_recovered'] ?? null
        ) {
            '0' => false,
            '1' => true,
            default => null,
        };

        if (
            $configurationRestored === null
            || $servicesRecovered === null
        ) {
            return null;
        }

        return new MarzbanHttpsRecoveryResult(
            configurationRestored: $configurationRestored,
            servicesRecovered: $servicesRecovered,
        );
    }

    private function nullable(?string $value): ?string
    {
        $value = trim($value ?? '');

        return $value === '' ? null : $value;
    }
}
