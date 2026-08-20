<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\WordPress\PublicEndpoint;

use App\Domain\PublicEndpoint\Exceptions\PublicEndpointOperationException;
use App\Domain\PublicEndpoint\ValueObjects\PublicEndpointDomain;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Application\WordPress\PublicEndpoint\DTOs\WordPressRuntimeConfiguration;
use App\Infrastructure\Application\WordPress\PublicEndpoint\DTOs\WordPressRuntimeMutation;
use App\Support\SSH\SSHTimeout;
use Illuminate\Support\Facades\Log;

final readonly class SshWordPressPublicEndpointRuntimeManager
{
    private const int ENVIRONMENT_UNAVAILABLE = 70;

    private const int CANDIDATE_INVALID = 71;

    private const int MUTATION_FAILED = 72;

    private const int VERIFICATION_FAILED = 73;

    private const int RECOVERY_FAILED = 74;

    private const int BUSY = 75;

    private const string INSPECT_COMMAND = <<<'BASH'
env_file='/opt/xdeploy/apps/wordpress/.env'

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

printf 'public_url=%s\n' "$(read_value 'XDEPLOY_WORDPRESS_PUBLIC_URL')"
BASH;

    private const string MUTATE_COMMAND = <<<'BASH'
set -u

mode=__XDEPLOY_MODE__
domain=__XDEPLOY_DOMAIN__
app_dir='/opt/xdeploy/apps/wordpress'
compose_file="$app_dir/docker-compose.yml"
env_file="$app_dir/.env"
backup_root="$app_dir/.xdeploy-backups/public-endpoint"
transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"
lock_file='/var/lock/xdeploy-wordpress-public-endpoint.lock'

candidate_dir=''
backup_dir=''
mutation_started=0
workflow_finished=0
readiness_attempts=0
readiness_http_code=''
readiness_container_running=0
readiness_container_health='unknown'
verification_attempts=0
verification_http_code=''
verification_container_running=0
verification_container_health='unknown'
recovery_attempted=0
recovery_restored=0
recovery_recovered=0
recovery_readiness_attempts=0
recovery_readiness_http_code=''
recovery_container_running=0
recovery_container_health='unknown'

emit_failure() {
    stage="$1"
    restored="$2"
    recovered="$3"
    printf 'xdeploy_wordpress_endpoint_runtime=1\n'
    printf 'status=failed\n'
    printf 'stage=%s\n' "$stage"
    printf 'configuration_restored=%s\n' "$restored"
    printf 'services_recovered=%s\n' "$recovered"
    printf 'verification_attempts=%s\n' "$verification_attempts"
    printf 'verification_http_code=%s\n' "$verification_http_code"
    printf 'verification_container_running=%s\n' "$verification_container_running"
    printf 'verification_container_health=%s\n' "$verification_container_health"
    printf 'recovery_attempted=%s\n' "$recovery_attempted"
    printf 'recovery_readiness_attempts=%s\n' "$recovery_readiness_attempts"
    printf 'recovery_readiness_http_code=%s\n' "$recovery_readiness_http_code"
    printf 'recovery_container_running=%s\n' "$recovery_container_running"
    printf 'recovery_container_health=%s\n' "$recovery_container_health"
}

read_env_value() {
    wanted_key="$1"
    source_file="${2:-$env_file}"

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
' "$source_file"
}

compose() {
    docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -p xdeploy-wordpress \
        "$@"
}

runtime_readiness_probe() {
    readiness_http_code=''
    readiness_container_running=0
    readiness_container_health='unknown'

    container_id="$(compose ps -q wordpress 2>/dev/null)"

    if [ -z "$container_id" ]; then
        return 1
    fi

    if [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" != 'true' ]; then
        return 1
    fi

    readiness_container_running=1
    readiness_container_health="$(
        docker inspect \
            --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' \
            "$container_id" 2>/dev/null || true
    )"

    if [ "$readiness_container_health" != 'healthy' ]; then
        return 1
    fi

    public_url="$(read_env_value 'XDEPLOY_WORDPRESS_PUBLIC_URL')"
    request_host='localhost'
    forwarded_proto='http'

    if [ -n "$public_url" ]; then
        request_host="${public_url#https://}"
        request_host="${request_host%/}"
        forwarded_proto='https'
    fi

    readiness_http_code="$(
        curl --silent --show-error --output /dev/null \
            --write-out '%{http_code}' \
            --connect-timeout 1 \
            --max-time 2 \
            --header "Host: $request_host" \
            --header "X-Forwarded-Proto: $forwarded_proto" \
            'http://127.0.0.1:8080/' \
            2>/dev/null || true
    )"

    case "$readiness_http_code" in
        2??|3??) return 0 ;;
        *) return 1 ;;
    esac
}

wait_for_runtime_readiness() {
    readiness_attempts=0
    readiness_http_code=''
    readiness_container_running=0
    readiness_container_health='unknown'
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

atomic_restore() {
    source_file="$1"
    destination_file="$2"
    temporary_file="${destination_file}.xdeploy-restore.$$"
    cp -p "$source_file" "$temporary_file" && mv -f "$temporary_file" "$destination_file"
}

recreate_wordpress() {
    compose up -d --no-deps --force-recreate wordpress >/dev/null 2>&1
}

compensate() {
    recovery_attempted=1
    recovery_restored=0
    recovery_recovered=0
    recovery_readiness_attempts=0
    recovery_readiness_http_code=''
    recovery_container_running=0
    recovery_container_health='unknown'

    if [ -n "$backup_dir" ] && [ -r "$backup_dir/.env" ]; then
        if atomic_restore "$backup_dir/.env" "$env_file"; then
            recovery_restored=1

            if recreate_wordpress; then
                if wait_for_runtime_readiness; then
                    recovery_recovered=1
                fi

                recovery_readiness_attempts="$readiness_attempts"
                recovery_readiness_http_code="$readiness_http_code"
                recovery_container_running="$readiness_container_running"
                recovery_container_health="$readiness_container_health"
            fi
        fi
    fi

    if [ "$recovery_restored" -eq 1 ] && [ "$recovery_recovered" -eq 1 ]; then
        rm -f "$transaction_file"
        rm -rf "$backup_dir"
    fi

    return 0
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
    if (key == "XDEPLOY_WORDPRESS_PUBLIC_URL") {
        next
    }
    print
}
END {
    if (mode == "enable") {
        print "XDEPLOY_WORDPRESS_PUBLIC_URL=https://" domain
    }
}
' "$env_file" >"$candidate_env"; then
    emit_failure candidate 0 0
    exit 71
fi

if [ "$mode" = 'enable' ]; then
    grep -Fxq "XDEPLOY_WORDPRESS_PUBLIC_URL=https://$domain" "$candidate_env" || {
        emit_failure candidate 0 0
        exit 71
    }
elif grep -Eq '^[[:space:]]*XDEPLOY_WORDPRESS_PUBLIC_URL[[:space:]]*=' "$candidate_env"; then
    emit_failure candidate 0 0
    exit 71
fi

if ! compose_candidate="$(
    docker compose \
        --env-file "$candidate_env" \
        -f "$compose_file" \
        -p xdeploy-wordpress \
        config 2>/dev/null
)"; then
    emit_failure candidate 0 0
    exit 71
fi

if ! printf '%s\n' "$compose_candidate" |
    grep -Fq 'WORDPRESS_CONFIG_EXTRA:'; then
    emit_failure candidate 0 0
    exit 71
fi

if cmp -s "$candidate_env" "$env_file"; then
    workflow_finished=1
    printf 'xdeploy_wordpress_endpoint_runtime=1\n'
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
        compensate
        workflow_finished=1
        emit_failure mutation "$recovery_restored" "$recovery_recovered"
        exit 72
    }

if ! recreate_wordpress; then
    compensate
    workflow_finished=1
    emit_failure mutation "$recovery_restored" "$recovery_recovered"
    exit 72
fi

if ! wait_for_runtime_readiness; then
    verification_attempts="$readiness_attempts"
    verification_http_code="$readiness_http_code"
    verification_container_running="$readiness_container_running"
    verification_container_health="$readiness_container_health"

    compensate
    workflow_finished=1
    emit_failure verification "$recovery_restored" "$recovery_recovered"

    if [ "$recovery_restored" -eq 1 ] && [ "$recovery_recovered" -eq 1 ]; then
        exit 73
    fi

    exit 74
fi

workflow_finished=1
printf 'xdeploy_wordpress_endpoint_runtime=1\n'
printf 'status=prepared\n'
printf 'backup_token=%s\n' "$backup_token"
printf 'configuration_changed=1\n'
BASH;

    private const string RESTORE_COMMAND = <<<'BASH'
set -u

token=__XDEPLOY_BACKUP_TOKEN__
app_dir='/opt/xdeploy/apps/wordpress'
compose_file="$app_dir/docker-compose.yml"
env_file="$app_dir/.env"
backup_root="$app_dir/.xdeploy-backups/public-endpoint"
transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"
lock_file='/var/lock/xdeploy-wordpress-public-endpoint.lock'
backup_dir="$backup_root/$token"

if ! printf '%s\n' "$token" | grep -Eq '^runtime\.[A-Za-z0-9]+$'; then
    exit 74
fi

exec 9>"$lock_file" || exit 74
flock -n 9 || exit 75

if [ ! -r "$transaction_file" ] || [ -L "$transaction_file" ] ||
    [ "$(tr -d '\r\n' <"$transaction_file")" != "$token" ] ||
    [ ! -r "$backup_dir/.env" ] || [ -L "$backup_dir/.env" ]; then
    exit 74
fi

temporary="${env_file}.xdeploy-restore.$$"
cp -p "$backup_dir/.env" "$temporary" && mv -f "$temporary" "$env_file" || exit 74

compose() {
    docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -p xdeploy-wordpress \
        "$@"
}

compose up -d --no-deps --force-recreate wordpress >/dev/null 2>&1 || exit 74

attempt=1
while [ "$attempt" -le 30 ]; do
    container_id="$(compose ps -q wordpress 2>/dev/null)"

    if [ -n "$container_id" ] &&
        [ "$(docker inspect --format '{{.State.Running}}' "$container_id" 2>/dev/null)" = 'true' ] &&
        [ "$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container_id" 2>/dev/null)" = 'healthy' ]; then
        rm -f "$transaction_file"
        rm -rf "$backup_dir"
        exit 0
    fi

    if [ "$attempt" -lt 30 ]; then
        sleep 2
    fi

    attempt=$((attempt + 1))
done

exit 74
BASH;

    private const string COMMIT_COMMAND = <<<'BASH'
set -u

token=__XDEPLOY_BACKUP_TOKEN__
app_dir='/opt/xdeploy/apps/wordpress'
backup_root="$app_dir/.xdeploy-backups/public-endpoint"
transaction_file="$app_dir/.xdeploy-public-endpoint-transaction"
lock_file='/var/lock/xdeploy-wordpress-public-endpoint.lock'
backup_dir="$backup_root/$token"

if ! printf '%s\n' "$token" | grep -Eq '^runtime\.[A-Za-z0-9]+$'; then
    exit 74
fi

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

    public function inspect(): WordPressRuntimeConfiguration
    {
        $result = $this->privileged->executeWithResult(
            command: self::INSPECT_COMMAND,
            timeout: SSHTimeout::QUICK,
        );

        if (! $result->successful()) {
            throw PublicEndpointOperationException::environmentUnavailable();
        }

        $values = $this->parseKeyValues($result->output);

        return new WordPressRuntimeConfiguration(
            publicUrl: $this->nullable($values['public_url'] ?? null),
        );
    }

    public function prepareEnabled(
        PublicEndpointDomain $domain,
    ): WordPressRuntimeMutation {
        return $this->mutate('enable', $domain->value);
    }

    public function prepareDisabled(): WordPressRuntimeMutation
    {
        return $this->mutate('disable', '');
    }

    public function restore(WordPressRuntimeMutation $mutation): bool
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
            sensitive: true,
        );

        return $result->successful();
    }

    public function commit(WordPressRuntimeMutation $mutation): void
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
            sensitive: true,
        );

        if (! $result->successful()) {
            throw PublicEndpointOperationException::mutationFailed();
        }
    }

    private function mutate(
        string $mode,
        string $domain,
    ): WordPressRuntimeMutation {
        $command = str_replace(
            ['__XDEPLOY_MODE__', '__XDEPLOY_DOMAIN__'],
            [escapeshellarg($mode), escapeshellarg($domain)],
            self::MUTATE_COMMAND,
        );

        $result = $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::APPLICATION_INSTALL,
            sensitive: true,
        );

        $values = $this->parseKeyValues($result->output);

        if (! $result->successful()) {
            $this->logRuntimeFailure(
                values: $values,
                exitCode: $result->exitCode,
            );

            $recoveryAttempted = ($values['recovery_attempted'] ?? '0') === '1'
                || ($values['configuration_restored'] ?? '0') === '1'
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

        if (($values['xdeploy_wordpress_endpoint_runtime'] ?? null) !== '1'
            || ($values['status'] ?? null) !== 'prepared') {
            throw PublicEndpointOperationException::mutationFailed();
        }

        $changed = ($values['configuration_changed'] ?? '0') === '1';
        $token = $this->nullable($values['backup_token'] ?? null);

        if ($changed && $token === null) {
            throw PublicEndpointOperationException::mutationFailed();
        }

        return new WordPressRuntimeMutation(
            backupToken: $token,
            configurationChanged: $changed,
        );
    }

    /** @param array<string, string> $values */
    private function logRuntimeFailure(array $values, int $exitCode): void
    {
        $configurationRestored = $this->diagnosticBoolean(
            $values['configuration_restored'] ?? null,
        );
        $servicesRecovered = $this->diagnosticBoolean(
            $values['services_recovered'] ?? null,
        );

        $context = [
            'stage' => $this->diagnosticStage($values['stage'] ?? null),
            'exit_code' => $exitCode,
            'verification_attempts' => $this->diagnosticInteger(
                $values['verification_attempts'] ?? null,
            ),
            'verification_http_code' => $this->diagnosticHttpCode(
                $values['verification_http_code'] ?? null,
            ),
            'verification_container_running' => $this->diagnosticBoolean(
                $values['verification_container_running'] ?? null,
            ),
            'verification_container_health' => $this->diagnosticHealth(
                $values['verification_container_health'] ?? null,
            ),
            'recovery_attempted' => $this->diagnosticBoolean(
                $values['recovery_attempted'] ?? null,
            ),
            'configuration_restored' => $configurationRestored,
            'services_recovered' => $servicesRecovered,
            'recovery_readiness_attempts' => $this->diagnosticInteger(
                $values['recovery_readiness_attempts'] ?? null,
            ),
            'recovery_readiness_http_code' => $this->diagnosticHttpCode(
                $values['recovery_readiness_http_code'] ?? null,
            ),
            'recovery_container_running' => $this->diagnosticBoolean(
                $values['recovery_container_running'] ?? null,
            ),
            'recovery_container_health' => $this->diagnosticHealth(
                $values['recovery_container_health'] ?? null,
            ),
        ];

        if ($configurationRestored && $servicesRecovered) {
            Log::warning(
                'public_endpoint.wordpress.runtime_verification_failed',
                $context,
            );

            return;
        }

        Log::error(
            'public_endpoint.wordpress.runtime_verification_failed',
            $context,
        );
    }

    private function diagnosticStage(?string $value): string
    {
        return in_array(
            $value,
            ['environment', 'candidate', 'busy', 'mutation', 'verification'],
            true,
        ) ? $value : 'unknown';
    }

    private function diagnosticBoolean(?string $value): bool
    {
        return $value === '1';
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

    private function diagnosticHealth(?string $value): string
    {
        return in_array(
            $value,
            ['healthy', 'starting', 'unhealthy', 'none', 'unknown'],
            true,
        ) ? $value : 'unknown';
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
