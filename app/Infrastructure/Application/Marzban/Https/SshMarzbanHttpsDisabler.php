<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban\Https;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsRecoveryResult;
use App\Domain\Application\Marzban\Https\MarzbanHttpsDisabler;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteReaderInterface;
use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
use App\Domain\Platform\Caddy\Sites\Exceptions\CaddySiteMutationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Support\SSH\SSHTimeout;

final readonly class SshMarzbanHttpsDisabler implements MarzbanHttpsDisabler
{
    private const string SITE_KEY = 'marzban';

    private const string UPSTREAM = '127.0.0.1:8000';

    private const int ENVIRONMENT_UNAVAILABLE = 70;

    private const int CANDIDATE_INVALID = 71;

    private const int MUTATION_FAILED = 72;

    private const int VERIFICATION_FAILED = 73;

    private const int RECOVERY_FAILED = 74;

    private const int BUSY = 75;

    private const string DISABLE_RUNTIME_COMMAND = <<<'BASH'
set -u

marzban_path='/opt/marzban'
compose_file="$marzban_path/docker-compose.yml"
env_file="$marzban_path/.env"
backup_root="$marzban_path/.xdeploy-backups/https-disable"
lock_file='/var/lock/xdeploy-marzban-https-runtime.lock'
transaction_file="$marzban_path/.xdeploy-https-runtime-transaction"
candidate_dir=''
backup_dir=''
mutation_started=0
workflow_finished=0

emit_failure() {
    stage="$1"
    restored="$2"
    recovered="$3"

    printf 'xdeploy_marzban_https_disable=1\n'
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
    awk cmp cp curl docker flock grep install mkdir mktemp mv rm sleep; do
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

candidate_dir="$(mktemp -d "$marzban_path/.xdeploy-https-disable.XXXXXX")" || {
    emit_failure 'candidate' 0 0
    exit 71
}

candidate_env="$candidate_dir/.env"

if ! awk '
function environment_key(line, normalized) {
    normalized = line
    sub(/^[[:space:]]*export[[:space:]]+/, "", normalized)
    sub(/[[:space:]]*=.*/, "", normalized)

    return normalized
}

{
    key = environment_key($0)

    if (key == "UVICORN_UDS" ||
        key == "UVICORN_SSL_CERTFILE" ||
        key == "UVICORN_SSL_KEYFILE" ||
        key == "XRAY_SUBSCRIPTION_URL_PREFIX") {
        next
    }

    if (key == "UVICORN_HOST") {
        if (! host_written) {
            print "UVICORN_HOST=127.0.0.1"
            host_written = 1
        }

        next
    }

    if (key == "UVICORN_PORT") {
        if (! port_written) {
            print "UVICORN_PORT=8000"
            port_written = 1
        }

        next
    }

    print
}

END {
    if (! host_written) {
        print "UVICORN_HOST=127.0.0.1"
    }

    if (! port_written) {
        print "UVICORN_PORT=8000"
    }
}
' "$env_file" >"$candidate_env"; then
    emit_failure 'candidate' 0 0
    exit 71
fi

if grep -Eq '^[[:space:]]*(UVICORN_UDS|UVICORN_SSL_CERTFILE|UVICORN_SSL_KEYFILE|XRAY_SUBSCRIPTION_URL_PREFIX)[[:space:]]*=' "$candidate_env" ||
    ! grep -Fxq 'UVICORN_HOST=127.0.0.1' "$candidate_env" ||
    ! grep -Fxq 'UVICORN_PORT=8000' "$candidate_env"; then
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

configuration_changed=1

if cmp -s "$candidate_env" "$env_file"; then
    configuration_changed=0
fi

if [ "$configuration_changed" -eq 1 ]; then
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

    mutation_started=1

    if ! atomic_install "$candidate_env" "$env_file" 600; then
        recovery="$(compensate)"
        workflow_finished=1
        emit_failure 'mutation' "${recovery%%|*}" "${recovery#*|}"
        exit 72
    fi
fi

if [ "$configuration_changed" -eq 1 ] ||
    ! container_running ||
    ! local_runtime_ready; then
    if ! compose up -d --force-recreate >/dev/null 2>&1; then
        if [ "$mutation_started" -eq 1 ]; then
            recovery="$(compensate)"
            workflow_finished=1
            emit_failure 'mutation' "${recovery%%|*}" "${recovery#*|}"
        else
            emit_failure 'mutation' 1 0
        fi

        exit 72
    fi
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
    if [ "$mutation_started" -eq 1 ]; then
        recovery="$(compensate)"
        workflow_finished=1
        restored="${recovery%%|*}"
        recovered="${recovery#*|}"
    else
        restored=1
        recovered=0
    fi

    emit_failure 'verification' "$restored" "$recovered"

    if [ "$mutation_started" -eq 1 ] &&
        [ "$restored" -eq 1 ] &&
        [ "$recovered" -eq 1 ]; then
        exit 73
    fi

    exit 74
fi

workflow_finished=1

if [ -n "$backup_dir" ]; then
    rm -rf "$backup_dir"
fi

printf 'xdeploy_marzban_https_disable=1\n'
printf 'status=disabled\n'
printf 'configuration_changed=%s\n' "$configuration_changed"
BASH;

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
        private CaddySiteReaderInterface $caddySites,
        private CaddySiteManagerInterface $caddySiteManager,
    ) {}

    public function disable(MarzbanDomain $domain): void
    {
        $key = $this->siteKey();
        $site = $this->site($domain);

        if (! $this->caddySites->exists($key)) {
            return;
        }

        if (! $this->caddySites->matches($site)) {
            throw MarzbanHttpsApplyException::existingConfiguration();
        }

        try {
            $this->caddySiteManager->remove($key);
        } catch (CaddySiteMutationException $exception) {
            throw $this->mapCaddyMutationException($exception);
        }

        $result = $this->privileged->executeWithResult(
            command: self::DISABLE_RUNTIME_COMMAND,
            timeout: SSHTimeout::SLOW,
            sensitive: true,
        );

        if ($result->successful()) {
            return;
        }

        $runtimeRecovery = $this->runtimeRecovery($result->output);
        $siteRecovered = $this->restoreSite($site);

        if (! $siteRecovered) {
            throw MarzbanHttpsApplyException::mutationFailed(
                new MarzbanHttpsRecoveryResult(
                    configurationRestored: false,
                    servicesRecovered: false,
                ),
            );
        }

        throw $this->mapRuntimeFailure(
            exitCode: $result->exitCode,
            runtimeRecovery: $runtimeRecovery,
        );
    }

    private function siteKey(): CaddySiteKey
    {
        return CaddySiteKey::from(self::SITE_KEY);
    }

    private function site(MarzbanDomain $domain): CaddySite
    {
        return CaddySite::reverseProxy(
            key: $this->siteKey(),
            domain: $domain->value,
            upstream: self::UPSTREAM,
        );
    }

    private function restoreSite(CaddySite $site): bool
    {
        try {
            $this->caddySiteManager->upsert($site);

            return true;
        } catch (CaddySiteMutationException) {
            return false;
        }
    }

    private function runtimeRecovery(string $output): ?MarzbanHttpsRecoveryResult
    {
        $values = $this->parseKeyValueOutput($output);

        if (
            ! array_key_exists('configuration_restored', $values)
            && ! array_key_exists('services_recovered', $values)
        ) {
            return null;
        }

        return new MarzbanHttpsRecoveryResult(
            configurationRestored: ($values['configuration_restored'] ?? '0') === '1',
            servicesRecovered: ($values['services_recovered'] ?? '0') === '1',
        );
    }

    private function mapRuntimeFailure(
        int $exitCode,
        ?MarzbanHttpsRecoveryResult $runtimeRecovery,
    ): MarzbanHttpsApplyException {
        return match ($exitCode) {
            self::ENVIRONMENT_UNAVAILABLE => MarzbanHttpsApplyException::environmentUnavailable(),
            self::CANDIDATE_INVALID => MarzbanHttpsApplyException::candidateValidationFailed(),
            self::BUSY => MarzbanHttpsApplyException::operationInProgress(),
            self::VERIFICATION_FAILED,
            self::RECOVERY_FAILED => MarzbanHttpsApplyException::verificationFailed(
                $runtimeRecovery ?? new MarzbanHttpsRecoveryResult(
                    configurationRestored: false,
                    servicesRecovered: false,
                ),
            ),
            self::MUTATION_FAILED => MarzbanHttpsApplyException::mutationFailed(
                $runtimeRecovery,
            ),
            default => MarzbanHttpsApplyException::mutationFailed(
                $runtimeRecovery,
            ),
        };
    }

    private function mapCaddyMutationException(
        CaddySiteMutationException $exception,
    ): MarzbanHttpsApplyException {
        return match ($exception->failure) {
            CaddySiteMutationFailure::Environment => MarzbanHttpsApplyException::environmentUnavailable(),
            CaddySiteMutationFailure::CandidateValidation => MarzbanHttpsApplyException::candidateValidationFailed(),
            CaddySiteMutationFailure::Conflict => MarzbanHttpsApplyException::existingConfiguration(),
            CaddySiteMutationFailure::Busy => MarzbanHttpsApplyException::operationInProgress(),
            CaddySiteMutationFailure::Mutation,
            CaddySiteMutationFailure::Reload,
            CaddySiteMutationFailure::Recovery => MarzbanHttpsApplyException::mutationFailed(
                $exception->recoveryAttempted()
                    ? new MarzbanHttpsRecoveryResult(
                        configurationRestored: $exception->configurationRestored(),
                        servicesRecovered: $exception->serviceRecovered(),
                    )
                    : null,
            ),
        };
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyValueOutput(string $output): array
    {
        $values = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);

            if ($key === '') {
                continue;
            }

            $values[$key] = trim($value);
        }

        return $values;
    }
}
