<?php

declare(strict_types=1);

namespace App\Infrastructure\Application\Marzban;

use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsApplyException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsInspectionException;
use App\Domain\Application\Marzban\Exceptions\MarzbanHttpsPreflightException;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsApplyResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsDnsPreflightResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsPortInfo;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsRecoveryResult;
use App\Domain\Application\Marzban\Https\DTOs\MarzbanHttpsServerPreflightResult;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsLayoutState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortOwner;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsPortState;
use App\Domain\Application\Marzban\Https\Enums\MarzbanHttpsState;
use App\Domain\Application\Marzban\Https\MarzbanHttpsGateway;
use App\Domain\Application\Marzban\Https\ValueObjects\MarzbanDomain;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanCaddyfileFactory;
use App\Infrastructure\Application\Marzban\Configuration\MarzbanComposeOverrideFactory;
use App\Infrastructure\SSH\Contracts\SSHConnectionInterface;
use App\Domain\Server\Services\PrivilegedCommandExecutor;
use App\Support\SSH\SSHTimeout;

final readonly class SshMarzbanHttpsGateway implements MarzbanHttpsGateway
{
    private const int PUBLIC_ADDRESS_UNAVAILABLE = 41;

    private const int DNS_LOOKUP_UNAVAILABLE = 42;

    private const int SERVER_INSPECTION_UNAVAILABLE = 51;

    private const int APPLY_ENVIRONMENT_UNAVAILABLE = 70;

    private const int APPLY_CANDIDATE_INVALID = 71;

    private const int APPLY_MUTATION_FAILED = 72;

    private const int APPLY_VERIFICATION_FAILED = 73;

    private const int APPLY_RECOVERY_FAILED = 74;

    private const int APPLY_BUSY = 75;

    private const string INSPECT_COMMAND = <<<'BASH'
env_file='/opt/marzban/.env'
compose_file='/opt/marzban/docker-compose.yml'
overlay_file='/opt/marzban/docker-compose.xdeploy.yml'
caddyfile='/opt/marzban/Caddyfile'
socket_path='/var/lib/marzban/marzban.socket'
managed_marker='# xDeploy: marzban-https'

if [ ! -r "$env_file" ] || [ ! -r "$compose_file" ]; then
    exit 2
fi

read_value() {
    sed -n "s/^[[:space:]]*$1[[:space:]]*=[[:space:]]*//p" "$env_file" |
        tail -n 1 |
        tr -d '\r' |
        sed \
            -e "s/^[[:space:]]*//" \
            -e "s/[[:space:]]*$//" \
            -e "s/^[\"']//" \
            -e "s/[\"']$//"
}

emit() {
    printf '%s\n%s' "$1" "$2"
}

uds="$(read_value 'UVICORN_UDS')"
cert_file="$(read_value 'UVICORN_SSL_CERTFILE')"
key_file="$(read_value 'UVICORN_SSL_KEYFILE')"
subscription_url="$(read_value 'XRAY_SUBSCRIPTION_URL_PREFIX')"

has_cert=0
has_key=0
has_caddy_service=0
has_caddyfile=0
has_expected_proxy=0
has_managed_marker=0
uses_expected_socket=0
has_unreadable_overlay=0
has_overlay=0

[ -n "$cert_file" ] && has_cert=1
[ -n "$key_file" ] && has_key=1
[ -r "$caddyfile" ] && has_caddyfile=1
[ -e "$overlay_file" ] && has_overlay=1
[ "$uds" = "$socket_path" ] && uses_expected_socket=1

compose() {
    if [ -r "$overlay_file" ]; then
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            -f "$overlay_file" \
            "$@"
    else
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            "$@"
    fi
}

if [ -e "$overlay_file" ] && [ ! -r "$overlay_file" ]; then
    has_unreadable_overlay=1
fi

if compose config --services 2>/dev/null |
    grep -Fxq 'caddy'; then
    has_caddy_service=1
fi

if [ "$has_caddyfile" -eq 1 ]; then
    grep -Fq "$managed_marker" "$caddyfile" && has_managed_marker=1

    grep -Eq \
        'reverse_proxy[[:space:]]+unix//var/lib/marzban/marzban\.socket' \
        "$caddyfile" && has_expected_proxy=1
fi

https_reachable=0

case "$subscription_url" in
    https://*)
        if curl \
            --location \
            --silent \
            --output /dev/null \
            --connect-timeout 3 \
            --max-time 8 \
            "$subscription_url"
        then
            https_reachable=1
        fi
        ;;
esac

if [ "$has_managed_marker" -eq 1 ]; then
    if [ "$has_caddy_service" -eq 1 ] &&
        [ "$has_expected_proxy" -eq 1 ] &&
        [ "$uses_expected_socket" -eq 1 ] &&
        [ "$https_reachable" -eq 1 ]; then
        emit 'enabled' "$subscription_url"
    else
        emit 'misconfigured' "$subscription_url"
    fi

    exit 0
fi

if [ "$has_cert" -ne "$has_key" ]; then
    emit 'misconfigured' "$subscription_url"
    exit 0
fi

if [ "$has_cert" -eq 1 ]; then
    if [ ! -r "$cert_file" ] || [ ! -r "$key_file" ]; then
        emit 'misconfigured' "$subscription_url"
    else
        emit 'managed_externally' "$subscription_url"
    fi

    exit 0
fi

if [ "$https_reachable" -eq 1 ]; then
    emit 'managed_externally' "$subscription_url"
    exit 0
fi

if [ "$has_caddy_service" -eq 1 ] ||
    [ "$has_overlay" -eq 1 ] ||
    [ "$has_unreadable_overlay" -eq 1 ] ||
    [ "$has_caddyfile" -eq 1 ] ||
    [ "$has_expected_proxy" -eq 1 ] ||
    [ "$uses_expected_socket" -eq 1 ] ||
    [ -n "$subscription_url" ]; then
    emit 'misconfigured' "$subscription_url"
    exit 0
fi

emit 'disabled' ''
BASH;

    private const string SERVER_PREFLIGHT_COMMAND = <<<'BASH'
marzban_path='/opt/marzban'
compose_file='/opt/marzban/docker-compose.yml'
overlay_file='/opt/marzban/docker-compose.xdeploy.yml'
env_file='/opt/marzban/.env'
caddyfile='/opt/marzban/Caddyfile'
managed_marker='# xDeploy: marzban-https'

if ! command -v docker >/dev/null 2>&1 ||
    ! docker compose version >/dev/null 2>&1 ||
    ! command -v ss >/dev/null 2>&1; then
    exit 51
fi

layout_state='supported'
compose_services=''

compose() {
    if [ -r "$overlay_file" ]; then
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            -f "$overlay_file" \
            "$@"
    else
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            "$@"
    fi
}

if [ ! -d "$marzban_path" ]; then
    layout_state='missing'
elif [ ! -r "$marzban_path" ] || [ ! -x "$marzban_path" ] ||
    [ ! -e "$compose_file" ] || [ ! -e "$env_file" ]; then
    layout_state='unreadable'
elif [ ! -r "$compose_file" ] || [ ! -r "$env_file" ]; then
    layout_state='unreadable'
else
    if [ -e "$overlay_file" ] && [ ! -r "$overlay_file" ]; then
        layout_state='unreadable'
    else
        compose_services="$(
            compose config --services 2>/dev/null
        )" || layout_state='invalid_compose'
    fi

    if [ "$layout_state" = 'supported' ] &&
        ! printf '%s\n' "$compose_services" | grep -Fxq 'marzban'; then
        layout_state='unsupported_compose'
    fi

    if [ "$layout_state" = 'supported' ]; then
        compose_images="$(
            compose config --images 2>/dev/null
        )" || layout_state='invalid_compose'

        if [ "$layout_state" = 'supported' ] &&
            ! printf '%s\n' "$compose_images" |
                grep -Eq '(^|/)gozargah/marzban([:@]|$)'; then
            layout_state='unsupported_compose'
        fi
    fi
fi

managed_caddy=0
managed_caddy_id=''

if [ "$layout_state" = 'supported' ] &&
    printf '%s\n' "$compose_services" | grep -Fxq 'caddy' &&
    [ -r "$caddyfile" ] &&
    grep -Fq "$managed_marker" "$caddyfile"; then
    managed_caddy_id="$(
        compose ps -q caddy 2>/dev/null
    )"

    if [ -n "$managed_caddy_id" ] &&
        [ "$(
            docker inspect \
                --format '{{.State.Running}}' \
                "$managed_caddy_id" 2>/dev/null
        )" = 'true' ]; then
        managed_caddy=1
    fi
fi

docker_bindings="$(
    docker ps --format '{{.ID}}|{{.Names}}|{{.Ports}}' 2>/dev/null
)" || exit 51

classify_port() {
    port="$1"

    binding_id="$(
        printf '%s\n' "$docker_bindings" |
            awk -F '|' -v needle=":$port->" '
                index($3, needle) > 0 { print $1; exit }
            '
    )"

    if [ -n "$binding_id" ]; then
        same_managed_container=0

        if [ "$managed_caddy" -eq 1 ]; then
            case "$managed_caddy_id" in
                "$binding_id"*) same_managed_container=1 ;;
            esac

            case "$binding_id" in
                "$managed_caddy_id"*) same_managed_container=1 ;;
            esac
        fi

        if [ "$same_managed_container" -eq 1 ]; then
            printf 'managed|xdeploy_caddy'
        else
            printf 'conflict|docker'
        fi

        return
    fi

    if ! listener="$(
        ss -H -lntp "sport = :$port" 2>/dev/null
    )"; then
        printf 'unknown|unknown'

        return
    fi

    if [ -z "$listener" ]; then
        printf 'available|none'

        return
    fi

    listener_lower="$(printf '%s' "$listener" | tr '[:upper:]' '[:lower:]')"

    if [ "$managed_caddy" -eq 1 ] &&
        printf '%s' "$listener_lower" | grep -Fq 'caddy'; then
        printf 'managed|xdeploy_caddy'

        return
    fi

    if printf '%s' "$listener_lower" | grep -Fq 'nginx'; then
        printf 'conflict|nginx'
    elif printf '%s' "$listener_lower" |
        grep -Eq 'apache2|httpd'; then
        printf 'conflict|apache'
    elif printf '%s' "$listener_lower" | grep -Fq 'haproxy'; then
        printf 'conflict|haproxy'
    elif printf '%s' "$listener_lower" | grep -Fq 'caddy'; then
        printf 'conflict|caddy'
    elif printf '%s' "$listener_lower" |
        grep -Eq 'docker-proxy|containerd'; then
        printf 'conflict|docker'
    else
        printf 'conflict|other'
    fi
}

port_80="$(classify_port 80)"
port_443="$(classify_port 443)"

printf 'xdeploy_server_preflight=1\n'
printf 'layout_state=%s\n' "$layout_state"
printf 'managed_caddy=%s\n' "$managed_caddy"
printf 'port_80_state=%s\n' "${port_80%%|*}"
printf 'port_80_owner=%s\n' "${port_80#*|}"
printf 'port_443_state=%s\n' "${port_443%%|*}"
printf 'port_443_owner=%s\n' "${port_443#*|}"
BASH;

    private const string ENABLE_COMMAND = <<<'BASH'
domain=__XDEPLOY_DOMAIN__
compose_payload=__XDEPLOY_COMPOSE_PAYLOAD__
caddy_payload=__XDEPLOY_CADDY_PAYLOAD__

marzban_path='/opt/marzban'
compose_file="$marzban_path/docker-compose.yml"
overlay_file="$marzban_path/docker-compose.xdeploy.yml"
env_file="$marzban_path/.env"
caddyfile="$marzban_path/Caddyfile"
socket_path='/var/lib/marzban/marzban.socket'
backup_root="$marzban_path/.xdeploy-backups/https"
lock_file='/var/lock/xdeploy-marzban-https.lock'

candidate_dir=''
backup_dir=''
previous_overlay=0
previous_caddyfile=0
mutation_started=0
workflow_finished=0

emit_failure() {
    stage="$1"
    restored="$2"
    recovered="$3"

    printf 'xdeploy_https_apply=1\n'
    printf 'status=failed\n'
    printf 'stage=%s\n' "$stage"
    printf 'configuration_restored=%s\n' "$restored"
    printf 'services_recovered=%s\n' "$recovered"
}

compose_current() {
    docker compose \
        --env-file "$env_file" \
        -f "$compose_file" \
        -f "$overlay_file" \
        "$@"
}

compose_previous() {
    if [ "$previous_overlay" -eq 1 ]; then
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            -f "$overlay_file" \
            "$@"
    else
        docker compose \
            --env-file "$env_file" \
            -f "$compose_file" \
            "$@"
    fi
}

container_running() {
    container_id="$1"

    [ -n "$container_id" ] &&
        [ "$(
            docker inspect \
                --format '{{.State.Running}}' \
                "$container_id" 2>/dev/null
        )" = 'true' ]
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
    caddy_removed=1

    if [ "$previous_overlay" -eq 0 ] && [ -r "$overlay_file" ]; then
        current_caddy_id="$(
            compose_current ps -q caddy 2>/dev/null
        )"

        if [ -n "$current_caddy_id" ]; then
            docker rm -f "$current_caddy_id" >/dev/null 2>&1 ||
                caddy_removed=0
        fi
    fi

    restore_ok=1

    atomic_restore "$backup_dir/.env" "$env_file" || restore_ok=0

    if [ "$previous_overlay" -eq 1 ]; then
        atomic_restore \
            "$backup_dir/docker-compose.xdeploy.yml" \
            "$overlay_file" || restore_ok=0
    else
        rm -f "$overlay_file" || restore_ok=0
    fi

    if [ "$previous_caddyfile" -eq 1 ]; then
        atomic_restore "$backup_dir/Caddyfile" "$caddyfile" ||
            restore_ok=0
    else
        rm -f "$caddyfile" || restore_ok=0
    fi

    if [ "$restore_ok" -eq 1 ]; then
        configuration_restored=1
    fi

    if [ "$configuration_restored" -eq 1 ] &&
        [ "$caddy_removed" -eq 1 ] &&
        compose_previous up -d >/dev/null 2>&1; then
        previous_marzban_id="$(
            compose_previous ps -q marzban 2>/dev/null
        )"

        if container_running "$previous_marzban_id"; then
            services_recovered=1
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
        [ "$workflow_finished" -eq 0 ] &&
        [ -n "$backup_dir" ] && [ -d "$backup_dir" ]; then
        compensate >/dev/null 2>&1 || true
    elif [ "$mutation_started" -eq 0 ] &&
        [ -n "$backup_dir" ] && [ -d "$backup_dir" ]; then
        rm -rf "$backup_dir"
    fi

    cleanup
    exit "$exit_status"
}

trap on_exit EXIT HUP INT TERM
umask 077

for required_command in \
    awk base64 cp curl date docker flock grep install mkdir mktemp mv rm \
    sed sleep ss; do
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
    [ -L "$env_file" ] ||
    { [ -e "$overlay_file" ] && [ -L "$overlay_file" ]; } ||
    { [ -e "$caddyfile" ] && [ -L "$caddyfile" ]; }; then
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

candidate_dir="$(
    mktemp -d "$marzban_path/.xdeploy-https-candidate.XXXXXX"
)" || {
    emit_failure 'candidate' 0 0
    exit 71
}

candidate_env="$candidate_dir/.env"
candidate_overlay="$candidate_dir/docker-compose.xdeploy.yml"
candidate_caddyfile="$candidate_dir/Caddyfile"

if ! printf '%s' "$compose_payload" |
    base64 -d >"$candidate_overlay" ||
    ! printf '%s' "$caddy_payload" |
    base64 -d >"$candidate_caddyfile"; then
    emit_failure 'candidate' 0 0
    exit 71
fi

if ! awk \
    -v uds="$socket_path" \
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
        if (! uds_written) {
            print "UVICORN_UDS=" uds
            uds_written = 1
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
    if (! uds_written) {
        print "UVICORN_UDS=" uds
    }

    if (! subscription_written) {
        print "XRAY_SUBSCRIPTION_URL_PREFIX=" subscription_url
    }
}
' "$env_file" >"$candidate_env"; then
    emit_failure 'candidate' 0 0
    exit 71
fi

if ! grep -Fxq "UVICORN_UDS=$socket_path" "$candidate_env" ||
    ! grep -Fxq \
        "XRAY_SUBSCRIPTION_URL_PREFIX=https://$domain" \
        "$candidate_env" ||
    ! grep -Fq '# xDeploy: marzban-https' "$candidate_overlay" ||
    ! grep -Fq '# xDeploy: marzban-https' "$candidate_caddyfile" ||
    ! grep -Fq \
        'reverse_proxy unix//var/lib/marzban/marzban.socket' \
        "$candidate_caddyfile"; then
    emit_failure 'candidate' 0 0
    exit 71
fi

if ! docker compose \
    --env-file "$candidate_env" \
    -f "$compose_file" \
    -f "$candidate_overlay" \
    config --quiet >/dev/null 2>&1; then
    emit_failure 'candidate' 0 0
    exit 71
fi

candidate_services="$(
    docker compose \
        --env-file "$candidate_env" \
        -f "$compose_file" \
        -f "$candidate_overlay" \
        config --services 2>/dev/null
)" || {
    emit_failure 'candidate' 0 0
    exit 71
}

if ! printf '%s\n' "$candidate_services" | grep -Fxq 'marzban' ||
    ! printf '%s\n' "$candidate_services" | grep -Fxq 'caddy'; then
    emit_failure 'candidate' 0 0
    exit 71
fi

if ! docker run --rm \
    -v "$candidate_caddyfile:/etc/caddy/Caddyfile:ro" \
    caddy:2-alpine \
    caddy \
    validate \
    --config /etc/caddy/Caddyfile \
    --adapter caddyfile >/dev/null 2>&1; then
    emit_failure 'candidate' 0 0
    exit 71
fi

mkdir -p "$backup_root" || {
    emit_failure 'environment' 0 0
    exit 70
}

backup_dir="$(
    mktemp -d \
        "$backup_root/$(date -u '+%Y%m%dT%H%M%SZ').XXXXXX"
)" || {
    emit_failure 'environment' 0 0
    exit 70
}

if ! cp -p "$env_file" "$backup_dir/.env"; then
    emit_failure 'environment' 0 0
    exit 70
fi

if [ -e "$overlay_file" ]; then
    previous_overlay=1

    if ! cp -p \
        "$overlay_file" \
        "$backup_dir/docker-compose.xdeploy.yml"; then
        emit_failure 'environment' 0 0
        exit 70
    fi
fi

if [ -e "$caddyfile" ]; then
    previous_caddyfile=1

    if ! cp -p "$caddyfile" "$backup_dir/Caddyfile"; then
        emit_failure 'environment' 0 0
        exit 70
    fi
fi

mutation_started=1

if ! atomic_install "$candidate_env" "$env_file" 600 ||
    ! atomic_install "$candidate_overlay" "$overlay_file" 644 ||
    ! atomic_install "$candidate_caddyfile" "$caddyfile" 644; then
    recovery="$(compensate)"
    workflow_finished=1
    emit_failure \
        'mutation' \
        "${recovery%%|*}" \
        "${recovery#*|}"
    exit 72
fi

if ! compose_current up -d >/dev/null 2>&1; then
    recovery="$(compensate)"
    workflow_finished=1
    emit_failure \
        'mutation' \
        "${recovery%%|*}" \
        "${recovery#*|}"
    exit 72
fi

verification_succeeded=0
attempt=1

while [ "$attempt" -le 18 ]; do
    marzban_id="$(compose_current ps -q marzban 2>/dev/null)"
    caddy_id="$(compose_current ps -q caddy 2>/dev/null)"

    http_code=''

    if container_running "$marzban_id" &&
        container_running "$caddy_id" &&
        [ -n "$(ss -H -lnt 'sport = :443' 2>/dev/null)" ]; then
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
            2??|3??)
                verification_succeeded=1
                break
                ;;
        esac
    fi

    sleep 5
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

rm -rf "$backup_dir"
workflow_finished=1

printf 'xdeploy_https_apply=1\n'
printf 'status=enabled\n'
printf 'domain=%s\n' "$domain"
printf 'configuration_changed=1\n'
printf 'panel_url=https://%s/dashboard/\n' "$domain"
BASH;

    private const string DNS_PREFLIGHT_COMMAND = <<<'BASH'
domain=%s
server_ipv4=%s

if [ -z "$server_ipv4" ]; then
    server_ipv4="$(
        curl \
            --ipv4 \
            --fail \
            --silent \
            --show-error \
            --connect-timeout 4 \
            --max-time 8 \
            'https://api.ipify.org'
    )" || exit 41
fi

server_ipv4="$(printf '%%s' "$server_ipv4" | tr -d '\r\n')"

if [ -z "$server_ipv4" ]; then
    exit 41
fi

if ! command -v getent >/dev/null 2>&1; then
    exit 42
fi

printf 'xdeploy_dns_preflight=1\n'
printf 'server_ipv4=%%s\n' "$server_ipv4"

getent ahostsv4 "$domain" 2>/dev/null |
    awk '{print $1}' |
    sort -u |
    while IFS= read -r address; do
        [ -n "$address" ] && printf 'a=%%s\n' "$address"
    done

getent ahostsv6 "$domain" 2>/dev/null |
    awk 'tolower($1) !~ /^::ffff:/ {print $1}' |
    sort -u |
    while IFS= read -r address; do
        [ -n "$address" ] && printf 'aaaa=%%s\n' "$address"
    done
BASH;

    public function __construct(
        private SSHConnectionInterface $ssh,
        private PrivilegedCommandExecutor $privileged,
        private MarzbanComposeOverrideFactory $composeOverrideFactory,
        private MarzbanCaddyfileFactory $caddyfileFactory,
    ) {}

    public function inspect(): MarzbanHttpsInfo
    {
        $result = $this->privileged->executeWithResult(
            command: self::INSPECT_COMMAND,
            timeout: SSHTimeout::QUICK,
        );

        if (! $result->successful()) {
            throw MarzbanHttpsInspectionException::failed();
        }

        $lines = preg_split(
            pattern: '/\R/',
            subject: trim($result->output),
            limit: 2,
        );

        if ($lines === false) {
            throw MarzbanHttpsInspectionException::failed();
        }

        $state = MarzbanHttpsState::tryFrom(
            trim($lines[0] ?? ''),
        );

        if ($state === null) {
            throw MarzbanHttpsInspectionException::failed();
        }

        return new MarzbanHttpsInfo(
            state: $state,
            domain: $this->domainFromUrl($lines[1] ?? null),
        );
    }

    public function preflightDns(
        MarzbanDomain $domain,
        ?string $knownServerAddress = null,
    ): MarzbanHttpsDnsPreflightResult {
        $knownIpv4 = filter_var(
            $knownServerAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4
                | FILTER_FLAG_NO_PRIV_RANGE
                | FILTER_FLAG_NO_RES_RANGE,
        );

        $command = sprintf(
            self::DNS_PREFLIGHT_COMMAND,
            escapeshellarg($domain->value),
            escapeshellarg(is_string($knownIpv4) ? $knownIpv4 : ''),
        );

        $result = $this->ssh->executeWithResult(
            command: $command,
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw match ($result->exitCode) {
                self::PUBLIC_ADDRESS_UNAVAILABLE => MarzbanHttpsPreflightException::publicAddressUnavailable(),
                self::DNS_LOOKUP_UNAVAILABLE => MarzbanHttpsPreflightException::dnsLookupUnavailable(),
                default => MarzbanHttpsPreflightException::invalidResponse(),
            };
        }

        return $this->parseDnsPreflight(
            domain: $domain,
            output: $result->output,
        );
    }

    public function preflightServer(): MarzbanHttpsServerPreflightResult
    {
        $result = $this->privileged->executeWithResult(
            command: self::SERVER_PREFLIGHT_COMMAND,
            timeout: SSHTimeout::NORMAL,
        );

        if (! $result->successful()) {
            throw match ($result->exitCode) {
                self::SERVER_INSPECTION_UNAVAILABLE => MarzbanHttpsPreflightException::serverInspectionUnavailable(),
                default => MarzbanHttpsPreflightException::invalidResponse(),
            };
        }

        return $this->parseServerPreflight($result->output);
    }

    public function enable(
        MarzbanDomain $domain,
    ): MarzbanHttpsApplyResult {
        $command = strtr(
            self::ENABLE_COMMAND,
            [
                '__XDEPLOY_DOMAIN__' => escapeshellarg($domain->value),
                '__XDEPLOY_COMPOSE_PAYLOAD__' => escapeshellarg(
                    base64_encode(
                        $this->composeOverrideFactory->make(),
                    ),
                ),
                '__XDEPLOY_CADDY_PAYLOAD__' => escapeshellarg(
                    base64_encode(
                        $this->caddyfileFactory->make($domain),
                    ),
                ),
            ],
        );

        $result = $this->privileged->executeWithResult(
            command: $command,
            timeout: SSHTimeout::APPLICATION_INSTALL,
            sensitive: true,
        );

        $values = $this->parseKeyValueOutput($result->output);

        if ($result->successful()) {
            $panelUrl = "https://{$domain->value}/dashboard/";

            if (
                ($values['xdeploy_https_apply'] ?? null) !== '1'
                || ($values['status'] ?? null) !== 'enabled'
                || ($values['domain'] ?? null) !== $domain->value
                || ($values['configuration_changed'] ?? null) !== '1'
                || ($values['panel_url'] ?? null) !== $panelUrl
            ) {
                throw MarzbanHttpsApplyException::mutationFailed();
            }

            return new MarzbanHttpsApplyResult(
                domain: $domain->value,
                panelUrl: $panelUrl,
                configurationChanged: true,
            );
        }

        $hasApplyMarker =
            ($values['xdeploy_https_apply'] ?? null) === '1';

        $recovery = $hasApplyMarker
            ? $this->recoveryFromValues($values)
            : null;

        throw match ($result->exitCode) {
            self::APPLY_ENVIRONMENT_UNAVAILABLE => MarzbanHttpsApplyException::environmentUnavailable(),
            self::APPLY_CANDIDATE_INVALID => MarzbanHttpsApplyException::candidateValidationFailed(),
            self::APPLY_BUSY => MarzbanHttpsApplyException::operationInProgress(),
            self::APPLY_VERIFICATION_FAILED,
            self::APPLY_RECOVERY_FAILED => MarzbanHttpsApplyException::verificationFailed(
                $recovery ?? new MarzbanHttpsRecoveryResult(
                    configurationRestored: false,
                    servicesRecovered: false,
                ),
            ),
            self::APPLY_MUTATION_FAILED => MarzbanHttpsApplyException::mutationFailed($recovery),
            default => MarzbanHttpsApplyException::mutationFailed(),
        };
    }

    private function domainFromUrl(?string $url): ?string
    {
        $url = trim($url ?? '');

        if ($url === '') {
            return null;
        }

        $domain = parse_url($url, PHP_URL_HOST);

        if (! is_string($domain) || $domain === '') {
            return null;
        }

        return strtolower($domain);
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

    private function parseDnsPreflight(
        MarzbanDomain $domain,
        string $output,
    ): MarzbanHttpsDnsPreflightResult {
        $markerFound = false;
        $serverIpv4 = null;
        $resolvedIpv4 = [];
        $resolvedIpv6 = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            [$key, $value] = array_pad(
                explode('=', trim($line), 2),
                2,
                '',
            );

            if ($key === 'xdeploy_dns_preflight' && $value === '1') {
                $markerFound = true;

                continue;
            }

            if (
                $key === 'server_ipv4'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                        | FILTER_FLAG_NO_PRIV_RANGE
                        | FILTER_FLAG_NO_RES_RANGE,
                ) !== false
            ) {
                $serverIpv4 = $value;

                continue;
            }

            if (
                $key === 'a'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4,
                ) !== false
            ) {
                $resolvedIpv4[] = $value;

                continue;
            }

            if (
                $key === 'aaaa'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV6,
                ) !== false
                && ! $this->isIpv4MappedIpv6($value)
            ) {
                $resolvedIpv6[] = $value;
            }
        }

        if (! $markerFound || $serverIpv4 === null) {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        $resolvedIpv4 = array_values(array_unique($resolvedIpv4));
        $resolvedIpv6 = array_values(array_unique($resolvedIpv6));

        sort($resolvedIpv4);
        sort($resolvedIpv6);

        return new MarzbanHttpsDnsPreflightResult(
            domain: $domain->value,
            serverIpv4Address: $serverIpv4,
            resolvedIpv4Addresses: $resolvedIpv4,
            resolvedIpv6Addresses: $resolvedIpv6,
        );
    }

    private function parseServerPreflight(
        string $output,
    ): MarzbanHttpsServerPreflightResult {
        $values = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            [$key, $value] = array_pad(
                explode('=', trim($line), 2),
                2,
                '',
            );

            $values[$key] = $value;
        }

        if (($values['xdeploy_server_preflight'] ?? null) !== '1') {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        $layoutState = MarzbanHttpsLayoutState::tryFrom(
            $values['layout_state'] ?? '',
        );

        $port80 = $this->parsePortInfo(
            port: 80,
            values: $values,
        );

        $port443 = $this->parsePortInfo(
            port: 443,
            values: $values,
        );

        $managedCaddy = match ($values['managed_caddy'] ?? null) {
            '0' => false,
            '1' => true,
            default => null,
        };

        if ($layoutState === null || $managedCaddy === null) {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        return new MarzbanHttpsServerPreflightResult(
            layoutState: $layoutState,
            managedCaddyDetected: $managedCaddy,
            port80: $port80,
            port443: $port443,
        );
    }

    /**
     * @param  array<string, string>  $values
     */
    private function parsePortInfo(
        int $port,
        array $values,
    ): MarzbanHttpsPortInfo {
        $state = MarzbanHttpsPortState::tryFrom(
            $values["port_{$port}_state"] ?? '',
        );

        $owner = MarzbanHttpsPortOwner::tryFrom(
            $values["port_{$port}_owner"] ?? '',
        );

        if ($state === null || $owner === null) {
            throw MarzbanHttpsPreflightException::invalidResponse();
        }

        return new MarzbanHttpsPortInfo(
            port: $port,
            state: $state,
            owner: $owner,
        );
    }

    private function isIpv4MappedIpv6(string $address): bool
    {
        $packedAddress = inet_pton($address);

        if ($packedAddress === false || strlen($packedAddress) !== 16) {
            return false;
        }

        return substr($packedAddress, 0, 10) === str_repeat("\0", 10)
            && substr($packedAddress, 10, 2) === "\xff\xff";
    }
}
