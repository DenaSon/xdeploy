<?php

declare(strict_types=1);

namespace App\Infrastructure\Linux\Packages;

final class PackageManagerLockRetryCommand
{
    public const string BUSY_MARKER =
        '[xDeploy][package-manager][error] reason=package_manager_busy';

    private function __construct() {}

    public static function wrap(string $command): string
    {
        $wrapper = <<<'BASH'
PACKAGE_MANAGER_BUSY_EXIT_CODE=75
PACKAGE_MANAGER_MAX_ATTEMPTS=4
package_manager_output_file=''

package_manager_cleanup() {
    if [ -n "$package_manager_output_file" ]; then
        rm -f "$package_manager_output_file"
    fi
}

package_manager_is_busy() {
    printf '%s\n' "$1" \
        | grep -Eqi \
            'Could not get lock|Unable to acquire the dpkg frontend lock|Unable to lock directory|is another process using it'
}

package_manager_retry_delay() {
    case "$1" in
        1) printf '15\n' ;;
        2) printf '30\n' ;;
        *) printf '60\n' ;;
    esac
}

package_manager_run_command() (
export LC_ALL=C
__XDEPLOY_PACKAGE_COMMAND__
)

package_manager_attempt=1
trap package_manager_cleanup EXIT HUP INT TERM

while [ "$package_manager_attempt" -le "$PACKAGE_MANAGER_MAX_ATTEMPTS" ]; do
    package_manager_output_file="$(mktemp)"

    package_manager_run_command \
        >"$package_manager_output_file" 2>&1
    package_manager_exit_code=$?

    if [ "$package_manager_exit_code" -eq 0 ]; then
        cat "$package_manager_output_file"
        rm -f "$package_manager_output_file"
        package_manager_output_file=''
        trap - EXIT HUP INT TERM

        exit 0
    fi

    package_manager_output="$(cat "$package_manager_output_file")"
    package_manager_error_tail="$(tail -n 20 "$package_manager_output_file")"
    rm -f "$package_manager_output_file"
    package_manager_output_file=''

    if ! package_manager_is_busy "$package_manager_error_tail"; then
        printf '%s\n' "$package_manager_output" >&2
        trap - EXIT HUP INT TERM

        exit "$package_manager_exit_code"
    fi

    if [ "$package_manager_attempt" -ge "$PACKAGE_MANAGER_MAX_ATTEMPTS" ]; then
        printf '[xDeploy][package-manager][error] reason=package_manager_busy exit_code=%s\n' \
            "$PACKAGE_MANAGER_BUSY_EXIT_CODE" >&2
        trap - EXIT HUP INT TERM

        exit "$PACKAGE_MANAGER_BUSY_EXIT_CODE"
    fi

    package_manager_delay="$(
        package_manager_retry_delay "$package_manager_attempt"
    )"

    printf '[xDeploy][package-manager] busy attempt=%s retry_in=%ss\n' \
        "$package_manager_attempt" \
        "$package_manager_delay"

    sleep "$package_manager_delay"
    package_manager_attempt=$((package_manager_attempt + 1))
done
BASH;

        return str_replace(
            '__XDEPLOY_PACKAGE_COMMAND__',
            $command,
            $wrapper,
        );
    }
}
