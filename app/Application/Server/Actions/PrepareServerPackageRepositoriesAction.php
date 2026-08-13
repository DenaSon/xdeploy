<?php

declare(strict_types=1);

namespace App\Application\Server\Actions;

use App\Domain\Server\DTOs\OperatingSystemInfo;
use App\Domain\Server\Exceptions\ServerPackageRepositoryException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Models\Server;
use App\Support\SSH\SSHTimeout;

final readonly class PrepareServerPackageRepositoriesAction
{
    private const string ARVAN_PROVIDER = 'arvan';

    private const string DEFAULT_ARVAN_UBUNTU_MIRROR =
        'https://mirror.arvancloud.ir/ubuntu';

    public function __construct(
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function handle(
        Server $server,
        OperatingSystemInfo $operatingSystem,
    ): void {
        if (
            $server->cloud_provider !== self::ARVAN_PROVIDER
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

if ! apt-get \
    -o DPkg::Lock::Timeout=120 \
    -o Acquire::ForceIPv4=true \
    -o Acquire::Retries=3 \
    -o Acquire::http::Timeout=45 \
    -o Acquire::https::Timeout=45 \
    update; then
    rollback

    printf '[xDeploy][repositories][error] stage=%s exit_code=1\n' \
        "$stage" >&2

    exit 1
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
