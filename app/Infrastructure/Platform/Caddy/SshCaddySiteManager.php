<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Caddy;

use App\Domain\Platform\Caddy\Sites\CaddySite;
use App\Domain\Platform\Caddy\Sites\CaddySiteKey;
use App\Domain\Platform\Caddy\Sites\Contracts\CaddySiteManagerInterface;
use App\Domain\Platform\Caddy\Sites\DTOs\CaddySiteMutationResult;
use App\Domain\Platform\Caddy\Sites\Enums\CaddySiteMutationFailure;
use App\Domain\Platform\Caddy\Sites\Exceptions\CaddySiteMutationException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\Platform\Caddy\Configuration\CaddySiteConfigurationFactory;
use App\Support\SSH\SSHTimeout;

final readonly class SshCaddySiteManager implements CaddySiteManagerInterface
{
    private const int ENVIRONMENT_UNAVAILABLE = 70;

    private const int CANDIDATE_INVALID = 71;

    private const int MUTATION_FAILED = 72;

    private const int RELOAD_FAILED = 73;

    private const int RECOVERY_FAILED = 74;

    private const int BUSY = 75;

    private const string MUTATE_COMMAND = <<<'BASH'
action=__XDEPLOY_ACTION__
site_key=__XDEPLOY_SITE_KEY__
site_payload=__XDEPLOY_SITE_PAYLOAD__

caddyfile='/etc/caddy/Caddyfile'
managed_root='/etc/caddy/xdeploy'
sites_dir="$managed_root/sites"
site_file="$sites_dir/$site_key.caddy"
managed_marker='# xDeploy: caddy-platform'
managed_import='import xdeploy/sites/*.caddy'
site_marker="# xDeploy: caddy-site:$site_key"
lock_file='/var/lock/xdeploy-caddy-sites.lock'

candidate_dir=''
previous_file=''
previous_exists=0
mutation_started=0
reload_attempted=0
workflow_finished=0

emit_failure() {
    stage="$1"
    configuration_restored="$2"
    service_recovered="$3"

    printf 'xdeploy_caddy_site_mutation=1\n'
    printf 'status=failed\n'
    printf 'stage=%s\n' "$stage"
    printf 'configuration_restored=%s\n' "$configuration_restored"
    printf 'service_recovered=%s\n' "$service_recovered"
}

emit_success() {
    status="$1"
    changed="$2"

    printf 'xdeploy_caddy_site_mutation=1\n'
    printf 'status=%s\n' "$status"
    printf 'changed=%s\n' "$changed"
}

atomic_install() {
    source_file="$1"
    destination_file="$2"
    temporary_file="${destination_file}.xdeploy-new.$$"

    if install -m 0644 "$source_file" "$temporary_file" &&
        mv -f "$temporary_file" "$destination_file"; then
        return 0
    fi

    rm -f "$temporary_file"
    return 1
}

atomic_restore() {
    source_file="$1"
    destination_file="$2"
    temporary_file="${destination_file}.xdeploy-restore.$$"

    if cp -p "$source_file" "$temporary_file" &&
        mv -f "$temporary_file" "$destination_file"; then
        return 0
    fi

    rm -f "$temporary_file"
    return 1
}

compensate() {
    configuration_restored=0
    service_recovered=0
    restore_ok=1

    if [ "$previous_exists" -eq 1 ]; then
        atomic_restore "$previous_file" "$site_file" ||
            restore_ok=0
    else
        rm -f "$site_file" || restore_ok=0
    fi

    if [ "$restore_ok" -eq 1 ]; then
        configuration_restored=1
    fi

    if [ "$configuration_restored" -eq 1 ]; then
        if [ "$reload_attempted" -eq 1 ]; then
            if caddy validate \
                --config "$caddyfile" \
                --adapter caddyfile >/dev/null 2>&1 &&
                systemctl reload caddy >/dev/null 2>&1 &&
                systemctl is-active --quiet caddy; then
                service_recovered=1
            fi
        elif systemctl is-active --quiet caddy; then
            service_recovered=1
        fi
    fi

    printf '%s|%s' \
        "$configuration_restored" \
        "$service_recovered"
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
        [ "$workflow_finished" -eq 0 ] &&
        [ -n "$candidate_dir" ] &&
        [ -d "$candidate_dir" ]; then
        compensate >/dev/null 2>&1 || true
    fi

    cleanup
    exit "$exit_status"
}

trap on_exit EXIT HUP INT TERM
umask 077

for required_command in \
    base64 caddy cat chmod cmp cp flock grep install mkdir mktemp mv rm systemctl
do
    if ! command -v "$required_command" >/dev/null 2>&1; then
        emit_failure 'environment' 0 0
        exit 70
    fi
done

if [ "$action" != 'upsert' ] && [ "$action" != 'remove' ]; then
    emit_failure 'environment' 0 0
    exit 70
fi

if ! systemctl cat caddy.service >/dev/null 2>&1 ||
    [ ! -r "$caddyfile" ] ||
    [ -L "$caddyfile" ] ||
    ! grep -Fxq "$managed_marker" "$caddyfile" ||
    ! grep -Fxq "$managed_import" "$caddyfile" ||
    [ ! -d "$managed_root" ] ||
    [ -L "$managed_root" ] ||
    [ ! -d "$sites_dir" ] ||
    [ -L "$sites_dir" ]; then
    emit_failure 'environment' 0 0
    exit 70
fi

for current_site in "$sites_dir"/*.caddy; do
    [ -e "$current_site" ] || continue

    if [ -L "$current_site" ] ||
        [ ! -f "$current_site" ] ||
        [ ! -r "$current_site" ]; then
        emit_failure 'environment' 0 0
        exit 70
    fi
done

if [ -e "$site_file" ] &&
    { [ -L "$site_file" ] || [ ! -f "$site_file" ] || [ ! -r "$site_file" ]; }; then
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

if [ "$action" = 'remove' ] && [ ! -e "$site_file" ]; then
    workflow_finished=1
    emit_success 'unchanged' 0
    exit 0
fi

candidate_dir="$(
    mktemp -d "$managed_root/.candidate.XXXXXX"
)" || {
    emit_failure 'candidate' 0 0
    exit 71
}

chmod 0755 "$candidate_dir"

candidate_sites="$candidate_dir/sites"

if ! mkdir -m 0755 "$candidate_sites"; then
    emit_failure 'candidate' 0 0
    exit 71
fi

for current_site in "$sites_dir"/*.caddy; do
    [ -e "$current_site" ] || continue

    if ! cp -p "$current_site" "$candidate_sites/"; then
        emit_failure 'candidate' 0 0
        exit 71
    fi
done

if [ -e "$site_file" ]; then
    previous_exists=1
    previous_file="$candidate_dir/previous.caddy"

    if ! cp -p "$site_file" "$previous_file"; then
        emit_failure 'candidate' 0 0
        exit 71
    fi
fi

candidate_site="$candidate_sites/$site_key.caddy"

if [ "$action" = 'upsert' ]; then
    if ! printf '%s' "$site_payload" |
        base64 -d >"$candidate_site"; then
        emit_failure 'candidate' 0 0
        exit 71
    fi

    chmod 0644 "$candidate_site"

    if ! grep -Fxq "$site_marker" "$candidate_site"; then
        emit_failure 'candidate' 0 0
        exit 71
    fi

    if [ "$previous_exists" -eq 1 ] &&
        cmp -s "$candidate_site" "$site_file"; then
        workflow_finished=1
        emit_success 'unchanged' 0
        exit 0
    fi
else
    if ! rm -f "$candidate_site"; then
        emit_failure 'candidate' 0 0
        exit 71
    fi
fi

candidate_caddyfile="$candidate_dir/Caddyfile"

cat >"$candidate_caddyfile" <<'CADDY'
# xDeploy: caddy-platform
import sites/*.caddy
CADDY

chmod 0644 "$candidate_caddyfile"

if ! caddy validate \
    --config "$candidate_caddyfile" \
    --adapter caddyfile >/dev/null 2>&1; then
    emit_failure 'candidate' 0 0
    exit 71
fi

mutation_started=1

if [ "$action" = 'upsert' ]; then
    if ! atomic_install "$candidate_site" "$site_file"; then
        recovery="$(compensate)"
        workflow_finished=1

        emit_failure \
            'mutation' \
            "${recovery%%|*}" \
            "${recovery#*|}"

        if [ "${recovery%%|*}" -eq 1 ] &&
            [ "${recovery#*|}" -eq 1 ]; then
            exit 72
        fi

        exit 74
    fi
else
    if ! rm -f "$site_file"; then
        recovery="$(compensate)"
        workflow_finished=1

        emit_failure \
            'mutation' \
            "${recovery%%|*}" \
            "${recovery#*|}"

        if [ "${recovery%%|*}" -eq 1 ] &&
            [ "${recovery#*|}" -eq 1 ]; then
            exit 72
        fi

        exit 74
    fi
fi

reload_attempted=1

if ! systemctl reload caddy >/dev/null 2>&1 ||
    ! systemctl is-active --quiet caddy; then
    recovery="$(compensate)"
    workflow_finished=1

    emit_failure \
        'reload' \
        "${recovery%%|*}" \
        "${recovery#*|}"

    if [ "${recovery%%|*}" -eq 1 ] &&
        [ "${recovery#*|}" -eq 1 ]; then
        exit 73
    fi

    exit 74
fi

workflow_finished=1

if [ "$action" = 'upsert' ]; then
    emit_success 'applied' 1
else
    emit_success 'removed' 1
fi
BASH;

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
        private CaddySiteConfigurationFactory $configurationFactory,
    ) {}

    public function upsert(
        CaddySite $site,
    ): CaddySiteMutationResult {
        return $this->mutate(
            action: 'upsert',
            key: $site->key,
            payload: $this->configurationFactory->make($site),
        );
    }

    public function remove(
        CaddySiteKey $key,
    ): CaddySiteMutationResult {
        return $this->mutate(
            action: 'remove',
            key: $key,
            payload: '',
        );
    }

    private function mutate(
        string $action,
        CaddySiteKey $key,
        string $payload,
    ): CaddySiteMutationResult {
        $command = strtr(
            self::MUTATE_COMMAND,
            [
                '__XDEPLOY_ACTION__' => escapeshellarg($action),
                '__XDEPLOY_SITE_KEY__' => escapeshellarg($key->value),
                '__XDEPLOY_SITE_PAYLOAD__' => escapeshellarg(
                    base64_encode($payload),
                ),
            ],
        );

        $result = $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::NORMAL,
            sensitive: true,
        );

        $values = $this->parseKeyValueOutput(
            $result->output,
        );

        if ($result->successful()) {
            if (
                ($values['xdeploy_caddy_site_mutation'] ?? null) !== '1'
                || ! in_array(
                    $values['status'] ?? null,
                    [
                        'applied',
                        'removed',
                        'unchanged',
                    ],
                    true,
                )
                || ! in_array(
                    $values['changed'] ?? null,
                    [
                        '0',
                        '1',
                    ],
                    true,
                )
            ) {
                throw new CaddySiteMutationException(
                    CaddySiteMutationFailure::Environment,
                );
            }

            return new CaddySiteMutationResult(
                key: $key,
                changed: ($values['changed'] ?? null) === '1',
            );
        }

        $failure = match ($result->exitCode) {
            self::ENVIRONMENT_UNAVAILABLE => CaddySiteMutationFailure::Environment,
            self::CANDIDATE_INVALID => CaddySiteMutationFailure::CandidateValidation,
            self::MUTATION_FAILED => CaddySiteMutationFailure::Mutation,
            self::RELOAD_FAILED => CaddySiteMutationFailure::Reload,
            self::RECOVERY_FAILED => CaddySiteMutationFailure::Recovery,
            self::BUSY => CaddySiteMutationFailure::Busy,
            default => CaddySiteMutationFailure::Environment,
        };

        throw new CaddySiteMutationException(
            failure: $failure,
            configurationRestored: ($values['configuration_restored'] ?? null) === '1',
            serviceRecovered: ($values['service_recovered'] ?? null) === '1',
        );
    }

    /**
     * @return array<string, string>
     */
    private function parseKeyValueOutput(
        string $output,
    ): array {
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
