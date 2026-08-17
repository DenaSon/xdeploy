<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Cloud\Enums\CloudProviderType;
use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Domain\Server\Exceptions\ServerPackageRepositoryException;
use App\Domain\Server\Exceptions\SystemPackageManagerBusyException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;

final readonly class PrepareServerPackageRepositoriesAction
{
    private const string DEFAULT_ARVAN_UBUNTU_MIRROR =
        'https://mirror.arvancloud.ir/ubuntu';

    private const string PACKAGE_MANAGER_BUSY_MARKER =
        '[xDeploy][repositories][error] reason=package_manager_busy';

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function handle(
        Server $server,
        OperatingSystemInfo $operatingSystem,
    ): void {
        if (
            $server->cloud_provider !== CloudProviderType::Arvan
            || $operatingSystem->id !== 'ubuntu'
        ) {
            return;
        }

        $mirror = rtrim(
            (string) config(
                'cloud.providers.arvan.package_repositories.ubuntu_mirror',
                self::DEFAULT_ARVAN_UBUNTU_MIRROR,
            ),
            '/',
        );

        if (
            preg_match(
                '#\Ahttps://[a-z0-9.-]+(?:/[a-z0-9._~/-]*)?\z#i',
                $mirror,
            ) !== 1
        ) {
            throw new ServerPackageRepositoryException(
                'ArvanCloud Ubuntu mirror configuration is invalid.',
            );
        }

        $result = $this->privileged->executeWithResult(
            command: $this->command(
                $mirror,
            ),
            timeout: SSHTimeout::SYSTEM_PACKAGE_INSTALL,
        );

        if ($result->successful()) {
            return;
        }

        if (
            str_contains(
                $result->output,
                self::PACKAGE_MANAGER_BUSY_MARKER,
            )
        ) {
            throw new SystemPackageManagerBusyException;
        }

        $stage = 'unknown';

        if (
            preg_match(
                '/\[xDeploy\]\[repositories\]\[error\] stage=([a-z0-9_]+)/',
                $result->output,
                $matches,
            ) === 1
        ) {
            $stage = $matches[1];
        }

        throw new ServerPackageRepositoryException(
            sprintf(
                'Server package repository preparation failed during [%s].',
                $stage,
            ),
        );
    }

    private function command(string $mirror): string
    {
        $command = <<<'BASH'
set -Eeuo pipefail

mirror='__XDEPLOY_UBUNTU_MIRROR__'
ipv4_config='/etc/apt/apt.conf.d/99xdeploy-force-ipv4'
stage='initialization'
changed_files=()
created_ipv4_config=0
readonly APT_LOCK_TIMEOUT_SECONDS=30
readonly PACKAGE_MANAGER_BUSY_EXIT_CODE=75
readonly PACKAGE_MANAGER_MAX_ATTEMPTS=5

on_error() {
    local exit_code=$?

    printf '[xDeploy][repositories][error] stage=%s exit_code=%s\n' \
        "$stage" \
        "$exit_code" >&2

    exit "$exit_code"
}

rollback() {
    local file

    for file in "${changed_files[@]}"; do
        if [[ -f "${file}.xdeploy-original" ]]; then
            cp -a "${file}.xdeploy-original" "$file"
        fi
    done

    if [[ "$created_ipv4_config" == '1' ]]; then
        rm -f "$ipv4_config"
    fi
}

rewrite_source() {
    local file="$1"

    [[ -f "$file" ]] || return 0

    if ! grep -Eq \
        'https?://[^[:space:]]*archive\.ubuntu\.com/ubuntu/?|https?://security\.ubuntu\.com/ubuntu/?' \
        "$file"; then
        return 0
    fi

    if [[ ! -f "${file}.xdeploy-original" ]]; then
        cp -a "$file" "${file}.xdeploy-original"
    fi

    sed -E -i \
        -e "s#https?://[^[:space:]]*archive\.ubuntu\.com/ubuntu/?#${mirror}/#g" \
        -e "s#https?://security\.ubuntu\.com/ubuntu/?#${mirror}/#g" \
        "$file"

    changed_files+=("$file")
}

is_package_manager_busy() {
    local output="$1"

    printf '%s\n' "$output" \
        | grep -Eqi \
            'Could not get lock|Unable to acquire the dpkg frontend lock|Unable to lock directory|Waiting for cache lock|is another process using it'
}

retry_delay_for_attempt() {
    case "$1" in
        1) printf '15\n' ;;
        2) printf '30\n' ;;
        3) printf '60\n' ;;
        *) printf '90\n' ;;
    esac
}

run_apt() {
    local apt_stage="$1"
    shift

    local attempt=1
    local output_file
    local output
    local exit_code
    local delay

    while (( attempt <= PACKAGE_MANAGER_MAX_ATTEMPTS )); do
        output_file="$(mktemp)"

        if apt-get \
            -o "DPkg::Lock::Timeout=${APT_LOCK_TIMEOUT_SECONDS}" \
            -o Acquire::ForceIPv4=true \
            -o Acquire::Retries=3 \
            -o Acquire::http::Timeout=45 \
            -o Acquire::https::Timeout=45 \
            "$@" >"$output_file" 2>&1; then
            cat "$output_file"
            rm -f "$output_file"

            return 0
        fi

        exit_code=$?
        output="$(cat "$output_file")"
        rm -f "$output_file"

        if ! is_package_manager_busy "$output"; then
            printf '%s\n' "$output" >&2

            return "$exit_code"
        fi

        if (( attempt >= PACKAGE_MANAGER_MAX_ATTEMPTS )); then
            return "$PACKAGE_MANAGER_BUSY_EXIT_CODE"
        fi

        delay="$(retry_delay_for_attempt "$attempt")"

        printf '[xDeploy][repositories] package manager busy stage=%s attempt=%s retry_in=%ss\n' \
            "$apt_stage" \
            "$attempt" \
            "$delay"

        sleep "$delay"
        attempt=$((attempt + 1))
    done
}

trap on_error ERR

stage='operating_system'

# shellcheck disable=SC1091
. /etc/os-release

if [[ "${ID:-}" != 'ubuntu' ]]; then
    exit 0
fi

if [[ -z "${VERSION_CODENAME:-}" ]]; then
    printf '[xDeploy][repositories][error] stage=%s exit_code=1\n' \
        "$stage" >&2
    exit 1
fi

stage='mirror_health'

curl \
    --ipv4 \
    --http1.1 \
    --proto '=https' \
    --tlsv1.2 \
    --fail \
    --silent \
    --show-error \
    --location \
    --connect-timeout 10 \
    --max-time 20 \
    "${mirror}/dists/${VERSION_CODENAME}/InRelease" \
    -o /dev/null

stage='force_ipv4'

if [[ ! -f "$ipv4_config" ]]; then
    created_ipv4_config=1
fi

printf 'Acquire::ForceIPv4 "true";\n' > "$ipv4_config"

stage='rewrite_sources'
rewrite_source '/etc/apt/sources.list'
rewrite_source '/etc/apt/sources.list.d/ubuntu.sources'

stage='apt_update'

if run_apt apt_update update; then
    :
else
    exit_code=$?
    rollback

    if [[ "$exit_code" == "$PACKAGE_MANAGER_BUSY_EXIT_CODE" ]]; then
        printf '[xDeploy][repositories][error] reason=package_manager_busy stage=%s exit_code=%s\n' \
            "$stage" \
            "$exit_code" >&2
    else
        printf '[xDeploy][repositories][error] stage=%s exit_code=%s\n' \
            "$stage" \
            "$exit_code" >&2
    fi

    exit "$exit_code"
fi

trap - ERR

printf '[xDeploy][repositories] Ubuntu package repositories are ready.\n'
BASH;

        return str_replace(
            '__XDEPLOY_UBUNTU_MIRROR__',
            $mirror,
            $command,
        );
    }
}
