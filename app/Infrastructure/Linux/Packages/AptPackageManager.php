<?php

declare(strict_types=1);

namespace App\Infrastructure\Linux\Packages;

use App\Domain\Server\Contracts\SystemPackageManager;
use App\Domain\Server\Exceptions\InvalidSystemPackageException;
use App\Domain\Server\Exceptions\SystemPackageInstallationException;
use App\Domain\Server\Exceptions\SystemPackageManagerBusyException;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Support\SSH\SSHTimeout;

final readonly class AptPackageManager implements SystemPackageManager
{
    private const string PACKAGE_MANAGER_BUSY_MARKER =
        '[xDeploy][apt][error] reason=package_manager_busy';

    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
    ) {}

    public function isInstalled(
        string $package,
    ): bool {
        $package = $this->normalizePackage($package);

        $result = $this->ssh->executeWithResult(
            command: sprintf(
                "dpkg-query -W -f='\${Status}' -- %s 2>/dev/null",
                $this->quoteForPosixShell($package),
            ),
            timeout: SSHTimeout::QUICK,
        );

        return $result->successful()
            && trim($result->output) === 'install ok installed';
    }

    /**
     * @param  list<string>  $packages
     */
    public function install(
        array $packages,
    ): void {
        $packages = $this->normalizePackages($packages);

        if ($packages === []) {
            return;
        }

        $packageArguments = implode(
            ' ',
            array_map(
                fn (string $package): string => $this->quoteForPosixShell(
                    $package,
                ),
                $packages,
            ),
        );

        $result = $this->privileged->executeWithResult(
            command: $this->installCommand(
                $packageArguments,
            ),
            timeout: SSHTimeout::SYSTEM_PACKAGE_INSTALL,
        );

        if (! $result->successful()) {
            if (
                str_contains(
                    $result->output,
                    self::PACKAGE_MANAGER_BUSY_MARKER,
                )
            ) {
                throw new SystemPackageManagerBusyException;
            }

            throw new SystemPackageInstallationException(
                sprintf(
                    'Failed to install system packages [%s].',
                    implode(', ', $packages),
                ),
            );
        }

        $this->verifyInstalledPackages($packages);
    }

    private function installCommand(
        string $packageArguments,
    ): string {
        $command = <<<'BASH'
set -eu

export DEBIAN_FRONTEND=noninteractive
export LC_ALL=C

APT_LOCK_TIMEOUT_SECONDS=30
PACKAGE_MANAGER_BUSY_EXIT_CODE=75
PACKAGE_MANAGER_MAX_ATTEMPTS=5
output_file=''

cleanup() {
    if [ -n "$output_file" ]; then
        rm -f "$output_file"
    fi
}

is_package_manager_busy() {
    printf '%s\n' "$1" \
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
    stage="$1"
    shift
    attempt=1

    while [ "$attempt" -le "$PACKAGE_MANAGER_MAX_ATTEMPTS" ]; do
        output_file="$(mktemp)"

        if apt-get \
            -o "DPkg::Lock::Timeout=${APT_LOCK_TIMEOUT_SECONDS}" \
            "$@" >"$output_file" 2>&1; then
            cat "$output_file"
            rm -f "$output_file"
            output_file=''

            return 0
        fi

        exit_code=$?
        output="$(cat "$output_file")"
        rm -f "$output_file"
        output_file=''

        if ! is_package_manager_busy "$output"; then
            printf '%s\n' "$output" >&2

            return "$exit_code"
        fi

        if [ "$attempt" -ge "$PACKAGE_MANAGER_MAX_ATTEMPTS" ]; then
            printf '[xDeploy][apt][error] reason=package_manager_busy stage=%s exit_code=%s\n' \
                "$stage" \
                "$PACKAGE_MANAGER_BUSY_EXIT_CODE" >&2

            return "$PACKAGE_MANAGER_BUSY_EXIT_CODE"
        fi

        delay="$(retry_delay_for_attempt "$attempt")"

        printf '[xDeploy][apt] package manager busy stage=%s attempt=%s retry_in=%ss\n' \
            "$stage" \
            "$attempt" \
            "$delay"

        sleep "$delay"
        attempt=$((attempt + 1))
    done
}

trap cleanup EXIT HUP INT TERM

run_apt update update
run_apt install install -y -- __XDEPLOY_PACKAGES__

trap - EXIT HUP INT TERM
cleanup
BASH;

        return str_replace(
            '__XDEPLOY_PACKAGES__',
            $packageArguments,
            $command,
        );
    }

    /**
     * @param  list<string>  $packages
     */
    private function verifyInstalledPackages(
        array $packages,
    ): void {
        foreach ($packages as $package) {
            if ($this->isInstalled($package)) {
                continue;
            }

            throw new SystemPackageInstallationException(
                sprintf(
                    'System package [%s] installation verification failed.',
                    $package,
                ),
            );
        }
    }

    /**
     * @param  array<int, string>  $packages
     * @return list<string>
     */
    private function normalizePackages(
        array $packages,
    ): array {
        $normalized = [];

        foreach ($packages as $package) {
            $normalized[] = $this->normalizePackage(
                $package,
            );
        }

        return array_values(
            array_unique($normalized),
        );
    }

    private function normalizePackage(
        string $package,
    ): string {
        $package = trim($package);

        $this->validatePackageName($package);

        return $package;
    }

    private function validatePackageName(
        string $package,
    ): void {
        if (
            $package === ''
            || preg_match(
                '/^[a-z0-9][a-z0-9+.-]*$/',
                $package,
            ) !== 1
        ) {
            throw new InvalidSystemPackageException(
                $package,
            );
        }
    }

    /**
     * Quotes a value for the remote POSIX shell.
     *
     * xDeploy can run on Windows while remote commands are executed
     * using Bash on the target Linux server.
     */
    private function quoteForPosixShell(
        string $value,
    ): string {
        return "'"
            .str_replace(
                "'",
                "'\"'\"'",
                $value,
            )
            ."'";
    }
}
